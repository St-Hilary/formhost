<?php
// Posting an Angel Fund gift into MinistryPlatform: donor matching, then a
// Donation row and a Donation_Distribution row shaped like the ones the
// office already sees from Tithely and MP eGiving.

declare(strict_types=1);

require_once __DIR__ . '/angelfund-lib.php';
require_once __DIR__ . '/mp-client.php';

// ---- small helpers ---------------------------------------------------------

/** "4153421545" / "(415) 342-1545" / "+1 415 342 1545" -> "415-342-1545"; null if not a 10-digit US number. */
function af_normalize_phone(?string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10) {
        return null;
    }
    return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6);
}

function af_zip5(?string $zip): string
{
    return substr(preg_replace('/\D+/', '', (string) $zip), 0, 5);
}

function af_name_matches(array $row, string $first, string $last): bool
{
    if ($last === '' || strcasecmp(trim((string) ($row['Last_Name'] ?? '')), $last) !== 0) {
        return false;
    }
    if ($first === '') {
        return true;
    }
    foreach (['First_Name', 'Nickname'] as $col) {
        $v = trim((string) ($row[$col] ?? ''));
        if ($v !== '' && stripos($v, $first) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Decide which of the candidate Contact rows is the donor. Pure function.
 *
 * @param array $rows   candidate rows from af_mp_find_contact()'s query
 * @param array $donor  ['first','last','email','phone','zip']
 * @return array{contact:?array, multiple:bool, how:string}
 */
function af_pick_contact(array $rows, array $donor): array
{
    if (count($rows) === 0) {
        return ['contact' => null, 'multiple' => false, 'how' => 'none'];
    }
    if (count($rows) === 1) {
        return ['contact' => $rows[0], 'multiple' => false, 'how' => 'single'];
    }

    $email = strtolower(trim((string) ($donor['email'] ?? '')));
    $byEmail = $email === '' ? [] : array_values(array_filter(
        $rows,
        static fn ($r) => strtolower(trim((string) ($r['Email_Address'] ?? ''))) === $email
    ));
    if (count($byEmail) === 1) {
        return ['contact' => $byEmail[0], 'multiple' => false, 'how' => 'email'];
    }

    $pool   = $byEmail ?: $rows;
    $byName = array_values(array_filter(
        $pool,
        static fn ($r) => af_name_matches($r, (string) ($donor['first'] ?? ''), (string) ($donor['last'] ?? ''))
    ));
    if (count($byName) === 1) {
        return ['contact' => $byName[0], 'multiple' => false, 'how' => 'name'];
    }

    return ['contact' => null, 'multiple' => true, 'how' => 'multiple'];
}

// ---- MP lookups ------------------------------------------------------------

/**
 * Query MP for contacts that match the donor by email, phone, or name+ZIP,
 * then pick one with af_pick_contact().
 */
function af_mp_find_contact(MpClient $mp, array $donor): array
{
    $parts = [];

    $email = trim((string) ($donor['email'] ?? ''));
    if ($email !== '') {
        $parts[] = 'Contacts.Email_Address = ' . MpClient::q($email);
    }

    $phone = af_normalize_phone($donor['phone'] ?? null);
    if ($phone !== null) {
        $p = MpClient::q($phone);
        $parts[] = "(Contacts.Mobile_Phone = $p OR Household_ID_TABLE.Home_Phone = $p)";
    }

    $first = trim((string) ($donor['first'] ?? ''));
    $last  = trim((string) ($donor['last'] ?? ''));
    $zip5  = af_zip5($donor['zip'] ?? null);
    if ($last !== '' && $first !== '' && strlen($zip5) === 5) {
        $f = MpClient::q(str_replace(['%', '_'], ['[%]', '[_]'], $first) . '%');
        $parts[] = '(Contacts.Last_Name = ' . MpClient::q($last)
            . " AND (Contacts.First_Name LIKE $f OR Contacts.Nickname LIKE $f)"
            . ' AND Household_ID_TABLE_Address_ID_TABLE.Postal_Code LIKE ' . MpClient::q($zip5 . '%') . ')';
    }

    if (!$parts) {
        return ['contact' => null, 'multiple' => false, 'how' => 'none'];
    }

    $rows = $mp->get('Contacts', [
        'select' => 'Contacts.Contact_ID, Contacts.Display_Name, Contacts.First_Name, Contacts.Nickname, '
                  . 'Contacts.Last_Name, Contacts.Email_Address, Contacts.Mobile_Phone, Contacts.Donor_Record, '
                  . 'Household_ID_TABLE.Home_Phone, Household_ID_TABLE_Address_ID_TABLE.Postal_Code',
        'filter' => 'Contacts.Contact_Status_ID <> 3 AND (' . implode(' OR ', $parts) . ')',
        'top'    => 50,
    ]);

    return af_pick_contact($rows, $donor);
}

/** Donor_ID for a contact, creating the Donor record if the contact has none. */
function af_mp_ensure_donor(MpClient $mp, int $contactId, ?int $donorRecord): int
{
    if ($donorRecord) {
        return $donorRecord;
    }
    $existing = $mp->get('Donors', [
        'select' => 'Donor_ID',
        'filter' => "Contact_ID = $contactId",
        'top'    => 1,
    ]);
    if (!empty($existing[0]['Donor_ID'])) {
        return (int) $existing[0]['Donor_ID'];
    }
    $created = $mp->create('Donors', [array_merge(AF_MP_DONOR_DEFAULTS, [
        'Contact_ID'       => $contactId,
        'Setup_Date'       => af_today()->format('Y-m-d\TH:i:s'),
        'Cancel_Envelopes' => false,
    ])]);
    if (empty($created[0]['Donor_ID'])) {
        throw new MpApiException('Donor create returned no Donor_ID: ' . json_encode($created));
    }
    return (int) $created[0]['Donor_ID'];
}

/** Donation_ID already holding this Stripe transaction, or null. */
function af_mp_donation_exists(MpClient $mp, string $transactionCode): ?int
{
    $rows = $mp->get('Donations', [
        'select' => 'Donation_ID',
        'filter' => 'Transaction_Code = ' . MpClient::q($transactionCode),
        'top'    => 1,
    ]);
    return !empty($rows[0]['Donation_ID']) ? (int) $rows[0]['Donation_ID'] : null;
}

/**
 * The batch a gift belongs in: one per calendar day, coded to the school, so the
 * Donations page shows these gifts when scoped to St. Hilary School and the
 * nightly autobatch leaves them alone. Created on first use.
 */
function af_mp_batch_for(MpClient $mp, DateTimeImmutable $when, bool $isCard): int
{
    $day  = $when->setTimezone(new DateTimeZone(AF_TIMEZONE))->format('Y-m-d');
    $name = "$day Angel Fund (Stripe)";

    $rows = $mp->get('Batches', [
        'select' => 'Batch_ID',
        'filter' => 'Batch_Name = ' . MpClient::q($name),
        'top'    => 1,
    ]);
    if (!empty($rows[0]['Batch_ID'])) {
        return (int) $rows[0]['Batch_ID'];
    }

    $created = $mp->create('Batches', [[
        'Batch_Name'           => $name,
        'Setup_Date'           => $when->setTimezone(new DateTimeZone(AF_TIMEZONE))->format('Y-m-d\TH:i:s'),
        'Batch_Total'          => 0,
        'Item_Count'           => 0,
        'Batch_Entry_Type_ID'  => AF_MP_BATCH_ENTRY_TYPE,
        'Batch_Usage_Type_ID'  => AF_MP_BATCH_USAGE_TYPE,
        'Default_Program'      => AF_MP_PROGRAM_ID,
        'Congregation_ID'      => AF_MP_CONGREGATION_ID,
        'Default_Payment_Type' => $isCard ? AF_MP_PAYMENT_TYPE_CARD : AF_MP_PAYMENT_TYPE_ACH,
        'Currency'             => 'USD',
    ]]);
    if (empty($created[0]['Batch_ID'])) {
        throw new MpApiException('Batch create returned no Batch_ID: ' . json_encode($created));
    }
    return (int) $created[0]['Batch_ID'];
}

/** Bump the batch's running total and item count after a donation is added. */
function af_mp_batch_add(MpClient $mp, int $batchId, float $amount): void
{
    $rows = $mp->get('Batches', [
        'select' => 'Batch_ID, Batch_Total, Item_Count',
        'filter' => "Batch_ID = $batchId",
        'top'    => 1,
    ]);
    if (empty($rows[0])) {
        return;
    }
    $mp->update('Batches', [[
        'Batch_ID'    => $batchId,
        'Batch_Total' => round((float) $rows[0]['Batch_Total'] + $amount, 2),
        'Item_Count'  => (int) $rows[0]['Item_Count'] + 1,
    ]]);
}

// ---- notes -----------------------------------------------------------------

/** The block the office is used to seeing on unmatched gifts (same layout as MP eGiving). */
function af_donor_block(array $d): string
{
    $lines = [
        'First Name'  => $d['first'] ?? '',
        'Last Name'   => $d['last'] ?? '',
        'Email'       => $d['email'] ?? '',
        'Phone'       => $d['phone'] ?? '',
        'Address 1'   => $d['address1'] ?? '',
        'Address 2'   => $d['address2'] ?? '',
        'City'        => $d['city'] ?? '',
        'State'       => $d['state'] ?? '',
        'Postal Code' => $d['zip'] ?? '',
        'Country'     => $d['country'] ?? '',
    ];
    $out = '';
    foreach ($lines as $k => $v) {
        $out .= "$k: " . trim((string) $v) . "\r\n";
    }
    return $out;
}

function af_gift_summary(array $gift): string
{
    $bits = [$gift['campaign']];
    if (($gift['schedule'] ?? '') === 'monthly') {
        $n = (int) ($gift['payment_number'] ?? 0);
        $t = (int) ($gift['payments_total'] ?? 0);
        $bits[] = $n && $t ? "Monthly payment $n of $t" : 'Monthly payment';
    } else {
        $bits[] = 'One-time';
    }
    $bits[] = 'Added ' . AF_FEE_PERCENT . '%?: ' . (!empty($gift['cover_fees']) ? 'Yes' : 'No');
    if (!empty($gift['affiliation'])) {
        $bits[] = 'Affiliation: ' . $gift['affiliation'];
    }
    return implode(' | ', $bits);
}

// ---- the main entry point --------------------------------------------------

/**
 * Post one Stripe charge into MP as a Donation + Donation_Distribution.
 *
 * $gift keys:
 *   transaction_code  Stripe PaymentIntent id (idempotency key)
 *   subscription_code Stripe subscription id or null
 *   amount_cents, fee_cents, net_cents, balance_transaction
 *   date              DateTimeImmutable of the charge
 *   method            'card' | 'us_bank_account'
 *   brand             card brand or null
 *   donor             ['first','last','email','phone','address1','address2','city','state','zip','country']
 *   campaign, schedule, cover_fees, affiliation, payment_number, payments_total
 *   mp_donor_id       donor id remembered on the Stripe customer, or null
 *   stripe_customer   customer id, for the notes
 *
 * @return array{status:string, donation_id:int, distribution_id:?int, donor_id:int, contact_id:?int, matched:bool, how:string}
 */
function af_mp_post_gift(MpClient $mp, array $gift): array
{
    $existing = af_mp_donation_exists($mp, $gift['transaction_code']);
    if ($existing !== null) {
        return ['status' => 'duplicate', 'donation_id' => $existing, 'distribution_id' => null,
                'donor_id' => 0, 'contact_id' => null, 'matched' => false, 'how' => 'existing'];
    }

    // 1. who is the donor?
    $donorId   = null;
    $contactId = null;
    $multiple  = false;
    $how       = 'remembered';

    if (!empty($gift['mp_donor_id'])) {
        $donorId = (int) $gift['mp_donor_id'];
    } else {
        $pick = af_mp_find_contact($mp, $gift['donor']);
        $how  = $pick['how'];
        if ($pick['contact']) {
            $contactId = (int) $pick['contact']['Contact_ID'];
            $donorId   = af_mp_ensure_donor($mp, $contactId, isset($pick['contact']['Donor_Record']) ? (int) $pick['contact']['Donor_Record'] : null);
        } else {
            $multiple = $pick['multiple'];
            $donorId  = AF_MP_DEFAULT_DONOR_ID;
        }
    }
    $matched = $donorId !== AF_MP_DEFAULT_DONOR_ID;

    // 2. notes
    $notes = af_gift_summary($gift);
    if (!empty($gift['stripe_customer'])) {
        $notes .= ' | Stripe ' . $gift['stripe_customer'];
    }
    if (!$matched) {
        $notes = af_donor_block($gift['donor']) . $notes;
    }
    $notes = mb_substr($notes, 0, 500);

    $gateway = json_encode([
        'gross'  => round($gift['amount_cents'] / 100, 2),
        'fee'    => round(($gift['fee_cents'] ?? 0) / 100, 2),
        'net'    => round(($gift['net_cents'] ?? $gift['amount_cents']) / 100, 2),
        'method' => $gift['method'],
        'brand'  => $gift['brand'],
        'txn'    => $gift['balance_transaction'] ?? null,
    ]);

    $isCard = ($gift['method'] ?? '') === 'card';
    /** @var DateTimeImmutable $when */
    $when = $gift['date'];

    // 3. Donation, inside today's school-coded batch
    $amount  = round($gift['amount_cents'] / 100, 2);
    $batchId = af_mp_batch_for($mp, $when, $isCard);

    $donation = $mp->create('Donations', [[
        'Donor_ID'             => $donorId,
        'Batch_ID'             => $batchId,
        'Donation_Amount'      => round($gift['amount_cents'] / 100, 2),
        'Donation_Date'        => $when->setTimezone(new DateTimeZone(AF_TIMEZONE))->format('Y-m-d\TH:i:s'),
        'Payment_Type_ID'      => $isCard ? AF_MP_PAYMENT_TYPE_CARD : AF_MP_PAYMENT_TYPE_ACH,
        'Item_Number'          => $isCard ? strtoupper((string) ($gift['brand'] ?? 'CARD')) : 'ACH',
        'Notes'                => $notes,
        'Anonymous'            => false,
        'Transaction_Code'     => $gift['transaction_code'],
        'Subscription_Code'    => $gift['subscription_code'] ?? null,
        'Gateway_Response'     => $gateway,
        'Currency'             => 'USD',
        'Receipted'            => false,
        'Multiple_Donor_Match' => $multiple,
    ]]);
    if (empty($donation[0]['Donation_ID'])) {
        throw new MpApiException('Donation create returned no Donation_ID: ' . json_encode($donation));
    }
    $donationId = (int) $donation[0]['Donation_ID'];
    af_mp_batch_add($mp, $batchId, $amount);

    // 4. Distribution
    $dist = $mp->create('Donation_Distributions', [[
        'Donation_ID' => $donationId,
        'Amount'      => round($gift['amount_cents'] / 100, 2),
        'Program_ID'  => AF_MP_PROGRAM_ID,
        'Notes'       => mb_substr($notes, 0, 1000),
    ]]);
    $distId = !empty($dist[0]['Donation_Distribution_ID']) ? (int) $dist[0]['Donation_Distribution_ID'] : null;

    return [
        'status'          => 'recorded',
        'donation_id'     => $donationId,
        'distribution_id' => $distId,
        'donor_id'        => $donorId,
        'contact_id'      => $contactId,
        'matched'         => $matched,
        'how'             => $how,
        'batch_id'        => $batchId,
    ];
}
