(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var versionBlock = document.querySelector('#left .version');
        var title = document.querySelector('.app-title');

        if (!versionBlock || !title) {
            return;
        }

        var match = versionBlock.textContent.match(/[\d]+\.[\d]+\.[\d]+/);

        if (!match) {
            return;
        }

        versionBlock.style.display = 'none';

        var badge = document.createElement('span');
        badge.className = 'backend-suite-contao-version';
        badge.textContent = 'v' + match[0];
        title.insertAdjacentElement('afterend', badge);
    });
})();
