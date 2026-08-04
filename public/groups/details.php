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
    // Relabel the "Contact this Group" tab/form as a sign-up.
    // The widget renders into an open shadow root, so we reach in once it has
    // rendered. Edit the strings below to change the wording.
    (function () {
        var LABELS = {
            inquiryOptionLabel: 'Sign up for this group', // tab / form heading
            contactGroupButtonText: 'Sign up'             // submit button
            // The widget also uses inquiringButtonText ("Contacting...") while
            // submitting and contactButtonSubmitAnotherText ("Submit Another
            // Inquiry") after success. Add either key here to relabel it too.
        };

        var defaults = null;

        function relabel(widget, root) {
            var tab = root.querySelector('#inquireTab');
            if (!tab) return false;

            // Capture the widget's own wording before we overwrite it, so we can
            // tell an untouched button from one the widget has put into another
            // state (e.g. "Contacting...").
            if (defaults === null && widget._i18n) {
                defaults = {};
                for (var key in LABELS) defaults[key] = widget._i18n[key];
            }

            // Patch the widget's label data so any later re-render or form reset
            // uses the new wording too.
            if (widget._i18n) {
                for (var k in LABELS) widget._i18n[k] = LABELS[k];
            }

            for (var i = 0; i < tab.childNodes.length; i++) {
                var node = tab.childNodes[i];
                if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                    if (node.textContent.trim() !== LABELS.inquiryOptionLabel) {
                        node.textContent = LABELS.inquiryOptionLabel;
                    }
                    break;
                }
            }

            var button = root.querySelector('#contactGroupButton');
            if (button && (!defaults || button.value === defaults.contactGroupButtonText)) {
                button.value = LABELS.contactGroupButtonText;
            }

            return true;
        }

        function start() {
            var widget = document.querySelector('mpp-group-details');
            if (!widget || !widget.shadowRoot) return false;
            var root = widget.shadowRoot;
            if (!relabel(widget, root)) return false;
            // Re-apply if the widget re-renders (e.g. after an attribute change).
            new MutationObserver(function () { relabel(widget, root); })
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
