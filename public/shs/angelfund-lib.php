<?php
// Shared settings and helpers for the Angel Fund donation form.
// Edit the constants here each year; the page, the payment endpoint and the
// thank-you page all read from this file.

const AF_CAMPAIGN_YEAR = '2026-27';
const AF_PAY_BY        = '2027-05-29';   // every gift is paid in full by this date
const AF_PER_STUDENT   = 2250;           // suggested gift per student, in dollars
const AF_MAX_STUDENTS  = 4;              // radio choices go up to this many students
const AF_FEE_PERCENT   = 3;              // the optional "help cover processing fees" add-on
const AF_MIN_GIFT      = 1;              // dollars
const AF_MAX_GIFT      = 100000;         // dollars; guards against a typo like 22500
const AF_TIMEZONE      = 'America/Los_Angeles';
const AF_CURRENCY      = 'usd';
const AF_PRODUCT_NAME  = 'Angel Fund';   // Stripe product used for monthly plans

const AF_AFFILIATIONS = [
    'Current Parent',
    'Alumni Parent',
    'Alumnus / Alumna',
    'Grandparent',
    'Faculty / Staff',
    'Parishioner',
    'Friend of Saint Hilary School',
];

function af_today(): DateTimeImmutable
{
    return new DateTimeImmutable('today', new DateTimeZone(AF_TIMEZONE));
}

function af_pay_by(): DateTimeImmutable
{
    return new DateTimeImmutable(AF_PAY_BY . ' 23:59:59', new DateTimeZone(AF_TIMEZONE));
}

/**
 * The dates Stripe will charge a monthly plan that starts on $start and is
 * cancelled at $end. Stripe bills on the same day of the month as the first
 * charge, clamped to the last day of shorter months.
 *
 * @return DateTimeImmutable[]  at least one date
 */
function af_payment_dates(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $day = (int) $start->format('j');
    $dates = [];
    for ($m = 0; $m < 24; $m++) {
        $month = $start->modify("first day of +$m month");
        $candidate = $month->setDate(
            (int) $month->format('Y'),
            (int) $month->format('n'),
            min($day, (int) $month->format('t'))
        );
        if ($candidate > $end) {
            break;
        }
        $dates[] = $candidate;
    }
    return $dates ?: [$start];
}

function af_monthly_cents(int $totalCents, int $payments): int
{
    return (int) round($totalCents / max(1, $payments));
}

function af_with_fee(int $cents): int
{
    return (int) round($cents * (100 + AF_FEE_PERCENT) / 100);
}

function af_money(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

/** "September 2026 through May 2027" */
function af_month_range(array $dates): string
{
    $first = $dates[0]->format('F Y');
    $last  = end($dates)->format('F Y');
    return $first === $last ? $first : "$first through $last";
}
