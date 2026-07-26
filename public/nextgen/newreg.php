<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>St. Hilary Church - Making Jesus Matter to Everyone</title>
    <link href='https://fonts.googleapis.com/css?family=Open+Sans&display=swap' rel='stylesheet'>
    <link rel="icon" type="image/ico" href="/img/favicon.ico">
    <style>
        :root {
            /* Height of the white logo band. Keep it at or below the blank strip
               the form leaves above its first card, so nothing is hidden at rest. */
            --band-height: 96px;
            --logo-size: 76px;
        }
        html, body {
            height: 100%;
            margin: 0;
            background: #ffffff;
        }
        body {
            font-family: 'Open Sans', Arial, sans-serif;
        }
        .page {
            position: relative;
            height: 100%;
            background: #ffffff;
            overflow: hidden;
        }
        /*
         * The iframe fills the ENTIRE viewport - nothing sits beside or above it
         * taking up space. That means every pixel on screen is over the form, so
         * a scroll or swipe anywhere scrolls the form.
         *
         * Rendered at 150%: sized to 66.6667% (1 / 1.5) then scaled up, so the
         * scaled result lands back at exactly 100% of the container.
         */
        .page iframe {
            display: block;
            width: 66.6667%;
            height: 66.6667%;
            border: 0;
            background: #ffffff;
            transform: scale(1.5);
            transform-origin: 0 0;
        }
        /*
         * The logo floats ON TOP of the form rather than taking its own row.
         *
         * pointer-events: none is what makes this work: the browser skips this
         * element during hit-testing, so wheel and touch gestures that land on
         * the logo pass straight through to the iframe underneath and scroll the
         * form as expected. Form content scrolls beneath the band, the way a
         * sticky header behaves.
         */
        .brand {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: var(--band-height);
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }
        .brand img {
            display: block;
            width: var(--logo-size);
            height: var(--logo-size);
        }

        /* Trim the band on short screens so the form keeps as much room as possible */
        @media (max-height: 700px) {
            :root {
                --band-height: 72px;
                --logo-size: 58px;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <iframe
        src="https://forms.growthmethod.app/w3o-7b3Js2WHMY"
        title="St. Hilary NextGen Registration"
        allow="clipboard-write"></iframe>
    <div class="brand">
        <img src="/img/sthilary.png" width="128" height="128" alt="St. Hilary Church" />
    </div>
</div>
</body>
</html>
