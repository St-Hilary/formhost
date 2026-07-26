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
        .page {
            display: flex;
            flex-direction: row;
            height: 100%;
            background: #ffffff;
        }
        .brand {
            flex: 0 0 auto;
            padding: 30px 40px 30px 50px;
            background: #ffffff;
        }
        .brand img {
            display: block;
        }
        .form-area {
            flex: 1 1 auto;
            min-width: 0;
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

        /* Stack the logo above the form on narrow screens */
        @media (max-width: 900px) {
            .page {
                flex-direction: column;
            }
            .brand {
                padding: 20px 0 10px 30px;
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
