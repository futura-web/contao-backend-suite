(function () {
    'use strict';

    // Recommended SERP display lengths, roughly matching what Google
    // typically shows before truncating.
    var RULES = {
        pageTitle: { min: 40, max: 60 },
        description: { min: 120, max: 160 },
    };

    function classify(length, rule) {
        if (length < rule.min) {
            return 'backend-suite-char-count--short';
        }
        if (length > rule.max) {
            return 'backend-suite-char-count--long';
        }
        return 'backend-suite-char-count--ok';
    }

    function attachCounter(field, rule) {
        var counter = document.createElement('span');
        counter.className = 'backend-suite-char-count';
        field.insertAdjacentElement('afterend', counter);

        function update() {
            var length = field.value.length;
            counter.textContent = length + ' / ' + rule.min + '–' + rule.max + ' Zeichen';
            counter.className = 'backend-suite-char-count ' + classify(length, rule);
        }

        field.addEventListener('input', update);
        update();
    }

    document.addEventListener('DOMContentLoaded', function () {
        Object.keys(RULES).forEach(function (name) {
            var field = document.querySelector('[name="' + name + '"]');

            if (field && !field.dataset.backendSuiteCounted) {
                field.dataset.backendSuiteCounted = '1';
                attachCounter(field, RULES[name]);
            }
        });
    });
})();
