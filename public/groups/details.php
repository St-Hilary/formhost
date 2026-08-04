<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>St. Hilary Church - Making Jesus Matter to Everyone</title>
    <link href='https://fonts.googleapis.com/css?family=Open+Sans&display=swap' rel='stylesheet'>
    <link rel="icon" type="image/ico" href="/img/favicon.ico">
    <script id="MPWidgets" src="https://mp.sthilary.org/widgets/dist/MPWidgets.js"></script>
</head>
<body>
<img style="padding-top: 30px; padding-left: 50px;" src="/img/sthilary.png" width="128" height="128" />

<mpp-group-details returnurl="/groups" hidesignuptab="true"></mpp-group-details>

<script>
    // Rename the "Contact this Group" tab/form to "Sign up for this group".
    // The widget renders into an open shadow root, so we reach in and swap the
    // label's text node once the widget has rendered.
    (function () {
        var NEW_LABEL = 'Sign up for this group';

        function renameTab(root) {
            var tab = root.querySelector('#inquireTab');
            if (!tab) return false;
            for (var i = 0; i < tab.childNodes.length; i++) {
                var node = tab.childNodes[i];
                if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                    if (node.textContent.trim() !== NEW_LABEL) node.textContent = NEW_LABEL;
                    return true;
                }
            }
            return false;
        }

        function start() {
            var widget = document.querySelector('mpp-group-details');
            if (!widget || !widget.shadowRoot) return false;
            var root = widget.shadowRoot;
            if (!renameTab(root)) return false;
            // Re-apply if the widget re-renders (e.g. after an attribute change).
            new MutationObserver(function () { renameTab(root); })
                .observe(root, { childList: true, subtree: true });
            return true;
        }

        if (!start()) {
            var tries = 0;
            var timer = setInterval(function () {
                if (start() || ++tries > 200) clearInterval(timer);
            }, 100);
        }
    })();
</script>

</body>
</html>
