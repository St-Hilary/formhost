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
        html, body {
            height: 100%;
            margin: 0;
            background: #ffffff;
        }
        body {
            font-family: 'Open Sans', Arial, sans-serif;
        }
        /*
         * Logo sits above the form and the form fills every remaining pixel of
         * the viewport. Nothing flanks the iframe, so there is no dead strip
         * where a scroll gesture lands on the parent page and does nothing.
         */
        .page {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: #ffffff;
        }
        .brand {
            flex: 0 0 auto;
            padding: 20px 0 10px 50px;
            background: #ffffff;
        }
        .brand img {
            display: block;
        }
        .form-area {
            flex: 1 1 auto;
            min-height: 0;
            background: #ffffff;
            overflow: hidden;
        }
        /*
         * Render the form at 150%. The iframe is sized to 66.6667% (1 / 1.5) of
         * the container and then scaled up, so the scaled result still fills the
         * box exactly and the form's own scrollbar stays inside the visible area.
         */
        .form-area iframe {
            display: block;
            width: 66.6667%;
            height: 66.6667%;
            border: 0;
            background: #ffffff;
            transform: scale(1.5);
            transform-origin: 0 0;
        }

        /* Give the form more room on short screens */
        @media (max-height: 700px) {
            .brand {
                padding: 10px 0 6px 30px;
            }
            .brand img {
                width: 88px;
                height: 88px;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="brand">
        <img src="/img/sthilary.png" width="128" height="128" alt="St. Hilary Church" />
    </div>
    <div class="form-area">
        <iframe
            src="https://forms.growthmethod.app/w3o-7b3Js2WHMY"
            title="St. Hilary NextGen Registration"
            allow="clipboard-write"></iframe>
    </div>
</div>
</body>
</html>
