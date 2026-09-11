<?php
// Stripe -> MinistryPlatform bridge for Angel Fund gifts.
//
// Stripe calls this when money actually moves:
//   payment_intent.succeeded  one-time gifts (fires when ACH settles, too)
//   invoice.paid              each charge of a monthly plan
// Each successful charge becomes one Donation + one Donation_Distribution in MP.
// Anything that fails returns a 5xx so Stripe retries for up to three days.

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/angelfund-mp.php';

header('Content-Type: application/json');

function respond(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

$webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');
$secretKey     = getenv('STRIPE_SECRET_KEY');
if (!$webhookSecret || !$secretKey) {
    respond(500, ['error' => 'STRIPE_WEBHOOK_SECRET and STRIPE_SECRET_KEY must be set.']);
}

$payload = (string) file_get_contents('php://input');
try {
    $event = \Stripe\Webhook::constructEvent($payload, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '', $webhookSecret);
} catch (\Throwable $e) {
    respond(400, ['error' => 'Invalid signature']);
}

$stripe = new \Stripe\StripeClient($secretKey);

function af_is_angel_fund(array $meta): bool
{
    return str_starts_with((string) ($meta['campaign'] ?? ''), 'Angel Fund');
}

/** Turn a paid Stripe charge into the $gift array af_mp_post_gift() wants, then post it. */
function af_record_charge(\Stripe\StripeClient $stripe, \Stripe\Charge $charge, \Stripe\Customer $customer,
                          array $meta, ?string $subscriptionId, int $paymentNumber, int $paymentsTotal): array
{
    $bt = $charge->balance_transaction;
    if (is_string($bt)) {
        $bt = $stripe->balanceTransactions->retrieve($bt);
    }
    $pmd    = $charge->payment_method_details;
    $method = $pmd->type ?? 'card';
    $brand  = $method === 'card' ? ($pmd->card->brand ?? null) : null;

    $cm   = $customer->metadata ? $customer->metadata->toArray() : [];
    $addr = $customer->address;
    $nameParts = explode(' ', trim((string) $customer->name), 2);

    $gift = [
        'transaction_code'    => is_string($charge->payment_intent) ? $charge->payment_intent : $charge->payment_intent->id,
        'subscription_code'   => $subscriptionId,
        'amount_cents'        => (int) $charge->amount,
        'fee_cents'           => $bt ? (int) $bt->fee : 0,
        'net_cents'           => $bt ? (int) $bt->net : (int) $charge->amount,
        'balance_transaction' => $bt ? $bt->id : null,
        'date'                => new DateTimeImmutable('@' . $charge->created),
        'method'              => $method,
        'brand'               => $brand,
        'donor' => [
            'first'    => $cm['first_name'] ?? $nameParts[0] ?? '',
            'last'     => $cm['last_name']  ?? $nameParts[1] ?? '',
            'email'    => (string) $customer->email,
            'phone'    => (string) $customer->phone,
            'address1' => $addr->line1 ?? '',
            'address2' => $addr->line2 ?? '',
            'city'     => $addr->city ?? '',
            'state'    => $addr->state ?? '',
            'zip'      => $addr->postal_code ?? '',
            'country'  => $cm['country'] ?? ($addr->country ?? ''),
        ],
        'campaign'        => $meta['campaign'] ?? ('Angel Fund ' . AF_CAMPAIGN_YEAR),
        'schedule'        => $meta['schedule'] ?? ($subscriptionId ? 'monthly' : 'one_time'),
        'cover_fees'      => ($meta['cover_fees'] ?? 'no') === 'yes',
        'affiliation'     => $meta['affiliation'] ?? ($cm['affiliation'] ?? ''),
        'payment_number'  => $paymentNumber,
        'payments_total'  => $paymentsTotal,
        'mp_donor_id'     => isset($cm['mp_donor_id']) ? (int) $cm['mp_donor_id'] : null,
        'stripe_customer' => $customer->id,
    ];

    $mp     = MpClient::fromEnv();
    $result = af_mp_post_gift($mp, $gift);

    // Remember a real match on the customer so later monthly charges skip the lookup.
    if ($result['status'] === 'recorded' && $result['matched'] && empty($cm['mp_donor_id'])) {
        $stripe->customers->update($customer->id, ['metadata' => [
            'mp_donor_id'   => (string) $result['donor_id'],
            'mp_contact_id' => (string) ($result['contact_id'] ?? ''),
        ]]);
    }
    return $result;
}

try {
    switch ($event->type) {

        case 'payment_intent.succeeded':
            $pi   = $event->data->object;
            $meta = $pi->metadata ? $pi->metadata->toArray() : [];
            if (($meta['schedule'] ?? '') !== 'one_time' || !af_is_angel_fund($meta)) {
                respond(200, ['status' => 'ignored', 'reason' => 'not a one-time Angel Fund gift']);
            }
            $pi = $stripe->paymentIntents->retrieve($pi->id, ['expand' => ['latest_charge.balance_transaction']]);
            $customer = $stripe->customers->retrieve((string) $pi->customer);
            $result = af_record_charge($stripe, $pi->latest_charge, $customer, $meta, null, 1, 1);
            break;

        case 'invoice.paid':
            $invoice = $event->data->object;
            $sd = $invoice->parent->subscription_details ?? null;
            $meta = $sd && $sd->metadata ? $sd->metadata->toArray() : [];
            if (!$sd || !af_is_angel_fund($meta)) {
                respond(200, ['status' => 'ignored', 'reason' => 'not an Angel Fund subscription invoice']);
            }
            $subscriptionId = is_string($sd->subscription) ? $sd->subscription : $sd->subscription->id;

            // The PaymentIntent that paid this invoice, then its charge with fee/net.
            $invoice = $stripe->invoices->retrieve($invoice->id);
            $piId = null;
            foreach ($invoice->payments->data ?? [] as $p) {
                if (($p->status ?? '') === 'paid' && !empty($p->payment->payment_intent)) {
                    $piId = is_string($p->payment->payment_intent) ? $p->payment->payment_intent : $p->payment->payment_intent->id;
                    break;
                }
            }
            if (!$piId) {
                respond(200, ['status' => 'ignored', 'reason' => 'invoice has no paid payment intent']);
            }
            $pi = $stripe->paymentIntents->retrieve($piId, ['expand' => ['latest_charge.balance_transaction']]);
            $customer = $stripe->customers->retrieve((string) $invoice->customer);

            // Which payment of the plan is this? Count paid invoices up to and including this one.
            $paid = $stripe->invoices->all(['subscription' => $subscriptionId, 'status' => 'paid', 'limit' => 100]);
            $number = 0;
            foreach ($paid->data as $inv) {
                if ($inv->created <= $invoice->created) {
                    $number++;
                }
            }
            $result = af_record_charge($stripe, $pi->latest_charge, $customer, $meta, $subscriptionId,
                max(1, $number), (int) ($meta['payments'] ?? 0));
            break;

        default:
            respond(200, ['status' => 'ignored', 'reason' => 'event type not handled']);
    }
} catch (\Throwable $e) {
    error_log('Angel Fund webhook error (' . $event->type . ' ' . $event->id . '): ' . $e->getMessage());
    respond(500, ['error' => 'Failed to record gift; Stripe will retry.']);
}

error_log('Angel Fund webhook ' . $event->type . ': ' . json_encode($result));
respond(200, $result);
