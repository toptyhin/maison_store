/**
 * Russian mobile phone mask (+ 7 900 000 00 00) via IMask.
 * Leading trunk digit 8 is dropped (national 8… → … after +7).
 */
(function () {
    var SELECTOR = 'input[name="telephone"],input[name="phone"],input#phone';

    function bindPhone(input) {
        if (!input || input.type === 'hidden' || input.readOnly || input.disabled) return;
        if (input.dataset.phoneImaskBound) return;
        if (typeof IMask === 'undefined') return;

        input.dataset.phoneImaskBound = '1';

        var fixing = false;
        var mask = IMask(input, {
            mask: '+ 7 000 000 00 00',
            lazy: false,
            placeholderChar: '_',
            prepare: function (appended, masked) {
                if (appended === '8' && masked.unmaskedValue === '') return '';
                return appended;
            }
        });

        mask.on('accept', function () {
            if (fixing) return;
            var u = mask.unmaskedValue;
            if (u && u.charAt(0) === '8') {
                fixing = true;
                try {
                    mask.unmaskedValue = u.slice(1);
                } finally {
                    fixing = false;
                }
            }
        });
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        root.querySelectorAll(SELECTOR).forEach(bindPhone);
    }

    function init() {
        scan(document);

        var mo = new MutationObserver(function (records) {
            for (var r = 0; r < records.length; r++) {
                records[r].addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches(SELECTOR)) bindPhone(node);
                    scan(node);
                });
            }
        });
        mo.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
