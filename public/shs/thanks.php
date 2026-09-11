<?php
require __DIR__ . '/angelfund-lib.php';
$publishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <title>Thank You - Angel Fund - Saint Hilary School</title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/ico" href="/img/favicon.ico">
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #fff; }
        body { font-family: 'Open Sans', Arial, sans-serif; color: #1c1c1c; font-size: 16px; line-height: 1.5; }
        .wrap { max-width: 900px; margin: 0 auto; background: #dde3ee; min-height: 100vh; }
        .brand { background: #5b95e0; text-align: center; }
        .brand img { display: block; margin: 0 auto; width: 100%; max-width: 430px; background: #fff; }
        .content { padding: 24px; }
        .card { background: #f3f3f3; border-radius: 4px; padding: 28px; }
        h1 { font-size: 30px; font-weight: 400; margin: 0 0 12px; }
        a.btn { display: inline-block; margin-top: 16px; background: #3d6fc4; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 3px; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><img src="/img/angel-fund.jpg" alt="Angel Fund - Saint Hilary School"></div>
    <div class="content">
        <div class="card">
            <h1 id="title">Checking your payment...</h1>
            <p id="message"></p>
            <a class="btn" href="/shs/angelfund.php" id="again" hidden>Back to the Angel Fund form</a>
        </div>
    </div>
</div>
<script>
(function () {
    var key = <?= json_encode($publishableKey) ?>;
    var AF_YEAR = <?= json_encode(AF_CAMPAIGN_YEAR) ?>;
    var params = new URLSearchParams(window.location.search);
    var secret = params.get('payment_intent_client_secret');
    var title = document.getElementById('title'), msg = document.getElementById('message'), again = document.getElementById('again');

    function show(t, m, showAgain) { title.textContent = t; msg.textContent = m; again.hidden = !showAgain; }

    if (!key || !secret) { show('Thank you', 'Your gift to the Angel Fund is appreciated.', true); return; }

    Stripe(key).retrievePaymentIntent(secret).then(function (r) {
        var pi = r.paymentIntent;
        if (!pi) { show('Thank you', 'Your gift to the Angel Fund is appreciated.', true); return; }
        var amount = '$' + (pi.amount / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        var monthly = pi.description && pi.description.indexOf('monthly') !== -1;
        switch (pi.status) {
            case 'succeeded':
                show('Thank you!', 'Your ' + (monthly ? 'first monthly ' : '') + 'gift of ' + amount + ' to the Saint Hilary School Angel Fund '
                    + AF_YEAR + ' campaign was received. A receipt has been emailed to you.', false);
                break;
            case 'processing':
                show('Thank you!', 'Your ' + (monthly ? 'first monthly ' : '') + 'gift of ' + amount + ' is processing. Bank payments take a few '
                    + 'business days to clear, and you will receive an email when it completes.', false);
                break;
            case 'requires_payment_method':
                show('Payment was not completed', 'Your payment method was declined or could not be used. Please try again with another card or bank account.', true);
                break;
            default:
                show('Payment status: ' + pi.status, 'If you are not sure whether your gift went through, please contact the school office before trying again.', true);
        }
    });
})();
</script>
</body>
</html>
