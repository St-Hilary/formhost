<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta http-equiv="refresh" content="10;url=https://tinyurl.com/nextgenreg26">
    <title>St. Hilary Church - Making Jesus Matter to Everyone</title>
    <link href='https://fonts.googleapis.com/css?family=Open+Sans&display=swap' rel='stylesheet'>
    <link rel="icon" type="image/ico" href="/img/favicon.ico">
    <style>
        body {
            font-family: 'Open Sans', Arial, sans-serif;
            margin: 0;
        }
        .message {
            max-width: 800px;
            margin: 60px auto 0 auto;
            padding: 0 40px;
            text-align: center;
        }
        .message p {
            font-size: 32px;
            line-height: 1.5;
            color: #333;
        }
        .message .blessing {
            font-size: 28px;
            margin-top: 40px;
        }
        .message .redirect {
            font-size: 18px;
            color: #777;
            margin-top: 60px;
        }
    </style>
</head>
<body>
<img style="padding-top: 30px; padding-left: 50px;" src="/img/sthilary.png" width="128" height="128" />

<div class="message">
    <p>You are now registered with St. Hilary - please use one of the other Kiosks to check your child in for the program.</p>
    <p class="blessing">God Bless!</p>
    <p class="redirect">Returning to registration in <span id="countdown">10</span> seconds&hellip;</p>
</div>

<script>
    (function () {
        var target = 'https://tinyurl.com/nextgenreg26';
        var remaining = 10;
        var el = document.getElementById('countdown');

        var timer = setInterval(function () {
            remaining--;
            if (el) {
                el.textContent = remaining > 0 ? remaining : 0;
            }
            if (remaining <= 0) {
                clearInterval(timer);
                window.location.replace(target);
            }
        }, 1000);
    })();
</script>

</body>
</html>
