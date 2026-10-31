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

    function setup() {
        run();

        // Nur #header und #left beobachten (dort leben .app-title und
        // .version), statt des gesamten Dokuments - dieses Script laedt auf
        // JEDER Backend-Seite (siehe BackendAssetsListener), ein Observer
        // auf document.documentElement wuerde also auch jede Mutation im
        // Content-Bereich (z.B. TinyMCE) unnoetig mitbeobachten.
        var header = document.getElementById('header');
        var left = document.getElementById('left');
        var observer = new MutationObserver(run);

        if (header) {
            observer.observe(header, { childList: true, subtree: true });
        }

        if (left) {
            observer.observe(left, { childList: true, subtree: true });
        }
    }

    // setup() erst nach DOMContentLoaded aufrufen (nicht sofort im <head>):
    // #header/#left existieren vorher noch nicht, observe(null) wuerde
    // sofort eine Exception werfen und der Observer wuerde nie erstellt -
    // genau das war der Grund, warum das Reinit nach "Speichern" nie
    // funktioniert hat.
    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();
