(function () {
    'use strict';

    function run() {
        var versionBlock = document.querySelector('#left .version');
        var title = document.querySelector('.app-title');

        if (!versionBlock || !title) {
            return;
        }

        // Already done and the sidebar hasn't been reset since - nothing to do.
        if ('none' === versionBlock.style.display && title.nextElementSibling && title.nextElementSibling.classList.contains('backend-suite-contao-version')) {
            return;
        }

        var match = versionBlock.textContent.match(/[\d]+\.[\d]+\.[\d]+/);

        if (!match) {
            return;
        }

        versionBlock.style.display = 'none';

        var badge = title.nextElementSibling && title.nextElementSibling.classList.contains('backend-suite-contao-version')
            ? title.nextElementSibling
            : document.createElement('span');

        badge.className = 'backend-suite-contao-version';
        badge.textContent = 'v' + match[0];

        if (badge.parentNode !== title.parentNode) {
            title.insertAdjacentElement('afterend', badge);
        }
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    // document.documentElement statt document.body: das Script laeuft im
    // <head>, bevor <body> existiert - observe(null) wuerde sofort eine
    // Exception werfen und den Observer nie erstellen (genau das war der
    // Grund, warum das Reinit nach "Speichern" nie funktioniert hat).
    new MutationObserver(run).observe(document.documentElement, { childList: true, subtree: true });
})();
