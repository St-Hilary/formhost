<?php
require __DIR__ . '/angelfund-lib.php';

$publishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
$monthlyOpen    = af_today() <= af_pay_by();
$dates          = $monthlyOpen ? af_payment_dates(af_today(), af_pay_by()) : [af_today()];
$payments       = count($dates);
$monthRange     = af_month_range($dates);
$payByDisplay   = af_pay_by()->format('n/j/y');
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Angel Fund Campaign <?= $h(AF_CAMPAIGN_YEAR) ?> - Saint Hilary School</title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/ico" href="/img/favicon.ico">
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #ffffff; }
        body { font-family: 'Open Sans', Arial, sans-serif; color: #1c1c1c; font-size: 16px; line-height: 1.5; }
        .wrap { max-width: 900px; margin: 0 auto; background: #dde3ee; min-height: 100vh; }
        .brand { background: #5b95e0; padding: 0; text-align: center; }
        .brand img { display: block; margin: 0 auto; width: 100%; max-width: 430px; background: #fff; }
        .content { padding: 20px 24px 40px; }
        h1 { font-size: 34px; font-weight: 400; margin: 12px 0 10px; }
        h2 { font-size: 28px; font-weight: 400; margin: 28px 0 12px; }
        p { margin: 0 0 14px; }
        hr { border: 0; border-top: 1px solid #b9c2d3; margin: 24px 0 8px; }
        label.req::after, .req::after { content: " *"; color: #c0392b; }
        .field { margin: 16px 0; }
        .field > .lbl { display: block; font-weight: 600; margin-bottom: 6px; }
        .sub { font-size: 13px; color: #444; margin-top: 3px; }
        input[type=text], input[type=email], input[type=tel], input[type=number], select {
            font: inherit; padding: 6px 8px; border: 1px solid #8b93a3; border-radius: 3px; background: #fff; width: 100%;
        }
        input:focus, select:focus { outline: 2px solid #5b95e0; outline-offset: 0; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; }
        .row > div { flex: 1 1 120px; }
        .row > div.narrow { flex: 0 0 70px; }
        .row > div.wide { flex: 2 1 200px; }
        .choice { display: flex; align-items: center; gap: 8px; margin: 6px 0; }
        .choice input[type=number] { width: 160px; }
        .hint { background: #fff; border-left: 4px solid #5b95e0; padding: 8px 12px; margin-top: 8px; font-size: 14px; }
        .check { display: flex; align-items: flex-start; gap: 8px; margin: 22px 0; }
        .check input { margin-top: 4px; }
        .btn { font: inherit; font-weight: 600; padding: 10px 22px; border-radius: 3px; border: 0; cursor: pointer; }
        .btn-primary { background: #3d6fc4; color: #fff; }
        .btn-primary:disabled { opacity: .6; cursor: default; }
        .btn-pay { display: block; width: 100%; background: #635bff; color: #fff; padding: 14px; font-size: 18px; margin-top: 22px; }
        .btn-plain { background: #f0f0f0; border: 1px solid #999; color: #1c1c1c; padding: 8px 14px; }
        .error { color: #b3261e; background: #fdecea; border: 1px solid #f5c2c0; padding: 10px 12px; border-radius: 3px; margin: 14px 0; }
        .card { background: #f3f3f3; border-radius: 4px; padding: 24px 28px 28px; margin-top: 12px; }
        .summary { width: 100%; border-collapse: collapse; margin: 10px 0 20px; }
        .summary td { padding: 12px 0; border-top: 1px solid #ddd; }
        .summary td:last-child { text-align: right; }
        .summary tr.total td { font-weight: 600; border-bottom: 1px solid #ddd; }
        .actions { display: flex; gap: 6px; margin-top: 10px; align-items: center; }
        .stripe-badge { margin-left: auto; font-size: 13px; color: #555; }
        @media (max-width: 500px) { .content { padding: 16px; } h1 { font-size: 28px; } .card { padding: 18px; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><img src="/img/angel-fund.jpg" alt="Angel Fund - Saint Hilary School"></div>

    <div class="content">

        <!-- ============ STEP 1: the form ============ -->
        <div id="step-form">
            <h1>Angel Fund Campaign <?= $h(AF_CAMPAIGN_YEAR) ?></h1>
            <p>The Saint Hilary School Angel Fund is an annual fundraising effort that supports programs that
               give Saint Hilary School a strong advantage, helping us stand out among elementary and middle
               schools. We are calling upon each family to contribute $<?= number_format(AF_PER_STUDENT) ?> per
               student to this year's campaign. This donation is tax-deductible.</p>
            <p>If you choose to give monthly, we will divide your total gift evenly over the remaining months of
               the school year so it is paid in full by <?= $h($payByDisplay) ?>.</p>

            <form id="gift-form" novalidate>
                <div class="field">
                    <span class="lbl req">Amount</span>
                    <?php for ($n = 1; $n <= AF_MAX_STUDENTS; $n++): $amt = AF_PER_STUDENT * $n; ?>
                    <label class="choice">
                        <input type="radio" name="amountChoice" value="<?= $amt ?>" <?= $n === 1 ? 'checked' : '' ?>>
                        $<?= number_format($amt) ?> - Recommended amount for <?= $n ?> student<?= $n > 1 ? 's' : '' ?>
                    </label>
                    <?php endfor; ?>
                    <label class="choice">
                        <input type="radio" name="amountChoice" value="custom">
                        $ <input type="number" id="customAmount" min="<?= AF_MIN_GIFT ?>" max="<?= AF_MAX_GIFT ?>" step="1" placeholder="Other amount" aria-label="Other amount">
                    </label>
                </div>

                <div class="field">
                    <label class="lbl" for="schedule">Donation Schedule</label>
                    <select id="schedule" name="schedule" style="width:auto">
                        <option value="one_time">One-time</option>
                        <?php if ($monthlyOpen): ?>
                        <option value="monthly">Monthly during school year</option>
                        <?php endif; ?>
                    </select>
                    <div id="monthly-hint" class="hint" hidden></div>
                </div>

                <hr>
                <h2>Contact Information</h2>

                <div class="field">
                    <span class="lbl req">Name</span>
                    <div class="row">
                        <div class="narrow"><input type="text" name="prefix" id="prefix" autocomplete="honorific-prefix"><div class="sub">Prefix</div></div>
                        <div class="wide"><input type="text" name="firstName" id="firstName" required autocomplete="given-name"><div class="sub">First Name</div></div>
                        <div class="wide"><input type="text" name="lastName" id="lastName" required autocomplete="family-name"><div class="sub">Last Name</div></div>
                        <div class="narrow"><input type="text" name="suffix" id="suffix" autocomplete="honorific-suffix"><div class="sub">Suffix</div></div>
                    </div>
                </div>

                <div class="field">
                    <label class="lbl req" for="email">Email</label>
                    <input type="email" name="email" id="email" required placeholder="email@example.com" autocomplete="email" style="max-width:320px">
                </div>

                <div class="field">
                    <span class="lbl req">Address</span>
                    <input type="text" name="address1" id="address1" required autocomplete="address-line1"><div class="sub">Address Line 1</div>
                    <input type="text" name="address2" id="address2" autocomplete="address-line2" style="margin-top:10px"><div class="sub">Address Line 2</div>
                    <div class="row" style="margin-top:10px">
                        <div class="wide"><input type="text" name="city" id="city" required autocomplete="address-level2"><div class="sub">City</div></div>
                        <div><input type="text" name="state" id="state" required autocomplete="address-level1"><div class="sub">State/Province</div></div>
                        <div><input type="text" name="zip" id="zip" required autocomplete="postal-code"><div class="sub">ZIP/Postal Code</div></div>
                    </div>
                    <div class="row" style="margin-top:10px">
                        <div class="wide"><input type="text" name="country" id="country" value="United States" autocomplete="country-name"><div class="sub">Country</div></div>
                        <div class="wide"></div>
                    </div>
                </div>

                <div class="field">
                    <label class="lbl req" for="phone">Phone</label>
                    <input type="tel" name="phone" id="phone" required placeholder="212-555-1212" autocomplete="tel" style="max-width:320px">
                </div>

                <div class="field">
                    <label class="lbl req" for="affiliation">School Affiliation</label>
                    <select name="affiliation" id="affiliation" required style="width:auto">
                        <option value="">select one</option>
                        <?php foreach (AF_AFFILIATIONS as $a): ?>
                        <option value="<?= $h($a) ?>"><?= $h($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="check">
                    <input type="checkbox" name="coverFees" id="coverFees">
                    <span>Add <?= AF_FEE_PERCENT ?>% to my total amount to help cover the payment processing fees</span>
                </label>

                <div id="form-error" class="error" hidden></div>

                <button type="submit" class="btn btn-primary" id="continue-btn">Enter payment information</button>
            </form>
        </div>

        <!-- ============ STEP 2: summary and payment ============ -->
        <div id="step-pay" hidden>
            <div class="card">
                <h2 style="margin-top:0">Summary</h2>
                <table class="summary"><tbody id="summary-rows"></tbody></table>

                <h2>Payment Information &#128274;</h2>
                <p>Enter your payment information here</p>
                <div id="payment-element"></div>
                <div id="pay-error" class="error" hidden></div>
                <button type="button" class="btn btn-pay" id="pay-btn">Submit Payment</button>
                <div class="actions">
                    <button type="button" class="btn btn-plain" id="back-btn">&lt; Back</button>
                    <button type="button" class="btn btn-plain" id="cancel-btn">Cancel</button>
                    <span class="stripe-badge">Powered by <strong>stripe</strong></span>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    var PUBLISHABLE_KEY = <?= json_encode($publishableKey) ?>;
    var PAYMENTS   = <?= (int) $payments ?>;
    var MONTHS     = <?= json_encode($monthRange) ?>;
    var FEE_PCT    = <?= (int) AF_FEE_PERCENT ?>;
    var RETURN_URL = window.location.origin + '/shs/thanks.php';

    var stripe = PUBLISHABLE_KEY ? Stripe(PUBLISHABLE_KEY) : null;
    var elements = null;

    var $ = function (id) { return document.getElementById(id); };
    var form = $('gift-form');
    var customInput = $('customAmount');
    var customRadio = form.querySelector('input[name=amountChoice][value=custom]');

    function money(n) { return '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

    function chosenAmount() {
        var picked = form.querySelector('input[name=amountChoice]:checked');
        if (!picked) return NaN;
        if (picked.value === 'custom') return parseFloat(customInput.value);
        return parseFloat(picked.value);
    }

    function updateHint() {
        var hint = $('monthly-hint');
        if ($('schedule').value !== 'monthly') { hint.hidden = true; return; }
        var amt = chosenAmount();
        if (!(amt > 0)) { hint.textContent = 'Enter an amount to see your monthly gift.'; hint.hidden = false; return; }
        var monthly = Math.round(amt * 100 / PAYMENTS) / 100;
        var text = PAYMENTS + ' monthly payment' + (PAYMENTS > 1 ? 's' : '') + ' of ' + money(monthly) + ', ' + MONTHS + '.';
        if ($('coverFees').checked) text += ' With the ' + FEE_PCT + '% fee add-on each payment is ' + money(Math.round(monthly * 100 * (100 + FEE_PCT) / 100) / 100) + '.';
        hint.textContent = text;
        hint.hidden = false;
    }

    customInput.addEventListener('focus', function () { customRadio.checked = true; updateHint(); });
    customInput.addEventListener('input', function () { customRadio.checked = true; updateHint(); });
    form.querySelectorAll('input[name=amountChoice]').forEach(function (r) { r.addEventListener('change', updateHint); });
    $('schedule').addEventListener('change', updateHint);
    $('coverFees').addEventListener('change', updateHint);

    function showError(id, msg) { var el = $(id); el.textContent = msg; el.hidden = !msg; }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        showError('form-error', '');

        var amt = chosenAmount();
        if (!(amt > 0)) { showError('form-error', 'Please choose or enter a gift amount.'); customInput.focus(); return; }
        if (!form.reportValidity()) return;
        if (!stripe) { showError('form-error', 'The payment system is not configured yet.'); return; }

        var payload = {
            amount: amt,
            schedule: $('schedule').value,
            coverFees: $('coverFees').checked,
            prefix: $('prefix').value, firstName: $('firstName').value, lastName: $('lastName').value, suffix: $('suffix').value,
            email: $('email').value, address1: $('address1').value, address2: $('address2').value,
            city: $('city').value, state: $('state').value, zip: $('zip').value, country: $('country').value,
            phone: $('phone').value, affiliation: $('affiliation').value
        };

        var btn = $('continue-btn');
        btn.disabled = true; btn.textContent = 'One moment...';

        fetch('/shs/create-payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || 'Something went wrong.'); return j; }); })
        .then(function (data) {
            renderSummary(data);
            elements = stripe.elements({
                clientSecret: data.clientSecret,
                appearance: { theme: 'stripe', variables: { fontFamily: 'Open Sans, Arial, sans-serif', colorPrimary: '#3d6fc4' } }
            });
            var pe = elements.create('payment', { layout: 'tabs' });
            $('payment-element').innerHTML = '';
            pe.mount('#payment-element');
            $('step-form').hidden = true;
            $('step-pay').hidden = false;
            window.scrollTo(0, 0);
        })
        .catch(function (e) { showError('form-error', e.message); })
        .finally(function () { btn.disabled = false; btn.textContent = 'Enter payment information'; });
    });

    function row(label, value, total) {
        return '<tr' + (total ? ' class="total"' : '') + '><td>' + label + '</td><td>' + value + '</td></tr>';
    }

    function renderSummary(d) {
        var html = '';
        if (d.schedule === 'monthly') {
            html += row('Total gift', d.gift);
            html += row('Schedule', d.payments + ' monthly payments, ' + d.months);
            if (d.fee) html += row('Processing fee add-on (' + FEE_PCT + '%)', d.fee + ' per month');
            html += row('Charged today and each month', d.charge, true);
        } else {
            html += row('Amount', d.gift);
            if (d.fee) html += row('Processing fee add-on (' + FEE_PCT + '%)', d.fee);
            html += row('Total', d.charge, true);
        }
        $('summary-rows').innerHTML = html;
    }

    $('pay-btn').addEventListener('click', function () {
        showError('pay-error', '');
        var btn = $('pay-btn');
        btn.disabled = true; btn.textContent = 'Processing...';
        stripe.confirmPayment({
            elements: elements,
            confirmParams: { return_url: RETURN_URL },
            redirect: 'if_required'
        }).then(function (result) {
            if (result.error) {
                showError('pay-error', result.error.message);
                btn.disabled = false; btn.textContent = 'Submit Payment';
                return;
            }
            // No redirect was needed (cards); go to the thank-you page ourselves.
            var pi = result.paymentIntent;
            window.location = RETURN_URL + '?payment_intent=' + encodeURIComponent(pi.id) +
                '&payment_intent_client_secret=' + encodeURIComponent(pi.client_secret) +
                '&redirect_status=' + encodeURIComponent(pi.status);
        });
    });

    $('back-btn').addEventListener('click', function () {
        $('step-pay').hidden = true;
        $('step-form').hidden = false;
        window.scrollTo(0, 0);
    });
    $('cancel-btn').addEventListener('click', function () { window.location.reload(); });

    updateHint();
})();
</script>
</body>
</html>
