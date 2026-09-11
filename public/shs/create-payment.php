<?php
// Receives the Angel Fund form as JSON, validates it, and creates either a
// one-time Stripe PaymentIntent or a monthly Subscription that ends on the
// pay-by date. Returns the client secret the Payment Element needs.
//
// The browser never tells Stripe an amount. Everything is recomputed here.

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/angelfund-lib.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

function fail(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Method not allowed.');
}

$secretKey = getenv('STRIPE_SECRET_KEY');
if (!$secretKey) {
    fail(500, 'The payment system is not configured yet.');
}

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    fail(400, 'Bad request.');
}

$field = static fn (string $key, int $max = 200): string =>
    trim(mb_substr((string) ($in[$key] ?? ''), 0, $max));

// ---- contact information -------------------------------------------------

$prefix      = $field('prefix', 20);
$firstName   = $field('firstName');
$lastName    = $field('lastName');
$suffix      = $field('suffix', 20);
$email       = $field('email');
$address1    = $field('address1');
$address2    = $field('address2');
$city        = $field('city');
$state       = $field('state', 50);
$zip         = $field('zip', 20);
$country     = $field('country', 60);
$phone       = $field('phone', 40);
$affiliation = $field('affiliation', 60);

$missing = [];
foreach ([
    'first name' => $firstName, 'last name' => $lastName, 'email' => $email,
    'address' => $address1, 'city' => $city, 'state' => $state,
    'ZIP code' => $zip, 'phone' => $phone, 'school affiliation' => $affiliation,
] as $label => $value) {
    if ($value === '') {
        $missing[] = $label;
    }
}
if ($missing) {
    fail(422, 'Please fill in your ' . implode(', ', $missing) . '.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail(422, 'Please enter a valid email address.');
}
if (!in_array($affiliation, AF_AFFILIATIONS, true)) {
    fail(422, 'Please choose a school affiliation.');
}

$fullName = trim(implode(' ', array_filter([$prefix, $firstName, $lastName, $suffix])));

$countryCode = null;
if (in_array(strtolower(str_replace('.', '', $country)), ['', 'us', 'usa', 'united states', 'united states of america'], true)) {
    $countryCode = 'US';
}

// ---- amount and schedule -------------------------------------------------

$amountRaw = $in['amount'] ?? null;
if (!is_numeric($amountRaw)) {
    fail(422, 'Please enter a gift amount.');
}
$dollars = round((float) $amountRaw, 2);
if ($dollars < AF_MIN_GIFT || $dollars > AF_MAX_GIFT) {
    fail(422, sprintf('Please enter an amount between $%s and $%s.', number_format(AF_MIN_GIFT), number_format(AF_MAX_GIFT)));
}
$giftCents = (int) round($dollars * 100);

$schedule  = ($in['schedule'] ?? '') === 'monthly' ? 'monthly' : 'one_time';
$coverFees = !empty($in['coverFees']);

$payments    = 1;
$chargeCents = $giftCents;
$dates       = [af_today()];

if ($schedule === 'monthly') {
    if (af_today() > af_pay_by()) {
        fail(422, 'Monthly gifts are no longer available for this campaign. Please choose a one-time gift.');
    }
    $dates       = af_payment_dates(af_today(), af_pay_by());
    $payments    = count($dates);
    $chargeCents = af_monthly_cents($giftCents, $payments);
}
if ($coverFees) {
    $chargeCents = af_with_fee($chargeCents);
}

// ---- talk to Stripe ------------------------------------------------------

$stripe = new \Stripe\StripeClient($secretKey);

$metadata = [
    'campaign'       => 'Angel Fund ' . AF_CAMPAIGN_YEAR,
    'schedule'       => $schedule,
    'gift_total'     => af_money($giftCents),
    'cover_fees'     => $coverFees ? 'yes' : 'no',
    'payments'       => (string) $payments,
    'per_payment'    => af_money($chargeCents),
    'affiliation'    => $affiliation,
    'donor_name'     => $fullName,
    'donor_email'    => $email,
    'donor_phone'    => $phone,
    'donor_country'  => $country,
];
$description = sprintf('Angel Fund %s %s gift from %s', AF_CAMPAIGN_YEAR,
    $schedule === 'monthly' ? 'monthly' : 'one-time', $fullName);

try {
    $customer = $stripe->customers->create([
        'name'    => $fullName,
        'email'   => $email,
        'phone'   => $phone,
        'address' => array_filter([
            'line1'       => $address1,
            'line2'       => $address2,
            'city'        => $city,
            'state'       => $state,
            'postal_code' => $zip,
            'country'     => $countryCode,
        ]),
        'metadata' => [
            'affiliation' => $affiliation,
            'campaign'    => $metadata['campaign'],
            'first_name'  => $firstName,
            'last_name'   => $lastName,
            'prefix'      => $prefix,
            'suffix'      => $suffix,
            'country'     => $country,
        ],
    ]);

    if ($schedule === 'one_time') {
        $intent = $stripe->paymentIntents->create([
            'amount'               => $chargeCents,
            'currency'             => AF_CURRENCY,
            'customer'             => $customer->id,
            'payment_method_types' => ['card', 'us_bank_account'],
            'description'          => $description,
            'receipt_email'        => $email,
            'metadata'             => $metadata,
        ]);
        $clientSecret = $intent->client_secret;
    } else {
        $subscription = $stripe->subscriptions->create([
            'customer' => $customer->id,
            'items'    => [[
                'price_data' => [
                    'currency'    => AF_CURRENCY,
                    'unit_amount' => $chargeCents,
                    'recurring'   => ['interval' => 'month'],
                    'product'     => af_product_id($stripe),
                ],
            ]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'payment_method_types'        => ['card', 'us_bank_account'],
                'save_default_payment_method' => 'on_subscription',
            ],
            'cancel_at'    => af_pay_by()->getTimestamp(),
            'description'  => $description,
            'metadata'     => $metadata,
            'expand'       => ['latest_invoice.confirmation_secret'],
        ]);
        $clientSecret = $subscription->latest_invoice->confirmation_secret->client_secret;
    }
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('Angel Fund Stripe error: ' . $e->getMessage());
    fail(502, 'We could not start the payment. Please try again in a moment.');
}

echo json_encode([
    'clientSecret' => $clientSecret,
    'schedule'     => $schedule,
    'gift'         => af_money($giftCents),
    'charge'       => af_money($chargeCents),
    'fee'          => $coverFees ? af_money($chargeCents - ($schedule === 'monthly' ? af_monthly_cents($giftCents, $payments) : $giftCents)) : null,
    'payments'     => $payments,
    'months'       => af_month_range($dates),
    'total'        => af_money($chargeCents * $payments),
]);

/**
 * The Stripe product that monthly plans bill against. Set STRIPE_ANGEL_FUND_PRODUCT
 * to a product id to skip the lookup; otherwise find or create one by name.
 */
function af_product_id(\Stripe\StripeClient $stripe): string
{
    $fromEnv = getenv('STRIPE_ANGEL_FUND_PRODUCT');
    if ($fromEnv) {
        return $fromEnv;
    }
    $found = $stripe->products->search([
        'query' => sprintf("active:'true' AND name:'%s'", AF_PRODUCT_NAME),
        'limit' => 1,
    ]);
    if (count($found->data) > 0) {
        return $found->data[0]->id;
    }
    return $stripe->products->create(['name' => AF_PRODUCT_NAME])->id;
}
