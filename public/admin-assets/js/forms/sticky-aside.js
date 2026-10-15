/*
 * Where the side column of a two-column admin form (.admin-sticky-aside) sticks.
 * Short enough for the window: just under the fixed header. Taller: it scrolls with the
 * page until its end is in view above the Save bar, then stays (a negative sticky top).
 */
(function () {
    'use strict';

    function place() {
        const header = document.querySelector('.topbar, .app-header');
        const actions = document.querySelector('.admin-sticky-actions');
        const top = (header ? header.offsetHeight : 0) + 16;
        const bottom = (actions ? actions.offsetHeight : 0) + 16;

        document.querySelectorAll('.admin-sticky-aside').forEach(function (aside) {
            const fits = aside.offsetHeight <= window.innerHeight - top - bottom;
            aside.style.setProperty('--admin-aside-top', (fits ? top : window.innerHeight - bottom - aside.offsetHeight) + 'px');
        });
    }

    function start() {
        const asides = document.querySelectorAll('.admin-sticky-aside');
        if (!asides.length) return;
        place();
        window.addEventListener('resize', place);
        // Cards open and close (SEO box tabs, variants): the column changes height.
        if (window.ResizeObserver) {
            const observer = new ResizeObserver(place);
            asides.forEach(function (aside) { observer.observe(aside); });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
