(function () {
    'use strict';

    function toggle(show) {
        document.body.classList.toggle('backend-suite-show-ids', show);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Shift') {
            toggle(true);
        }
    });

    document.addEventListener('keyup', function (e) {
        if (e.key === 'Shift') {
            toggle(false);
        }
    });

    window.addEventListener('blur', function () {
        toggle(false);
    });
})();
