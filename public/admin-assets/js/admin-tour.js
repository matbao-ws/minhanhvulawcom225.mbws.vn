/*
 * Guided tour of the current admin page, on driver.js (libs/driver.js). The steps come from
 * App\Support\AdminTours as JSON in #admin-tour-config; their text is already escaped.
 *
 * - Next/Back buttons, the arrow keys, a progress counter, and Skip / Esc / the close button.
 * - A step whose element is not on the page, or not visible (a button this admin cannot use,
 *   a shipping carrier that is not set up), is left out. Several views render the same
 *   element twice, for desktop and for mobile; the visible one is used.
 * - The tour starts by itself the first time an admin opens the page (per browser), and again
 *   whenever AdminTours::VERSION is bumped. The "?" button in the header replays it at any time.
 */
(function () {
    'use strict';

    const config = document.getElementById('admin-tour-config');
    const factory = window.driver && window.driver.js && window.driver.js.driver;
    if (!config || typeof factory !== 'function') {
        return;
    }

    let tour;
    try {
        tour = JSON.parse(config.textContent);
    } catch (e) {
        return;
    }

    const storageKey = 'admin-tour:' + tour.user + ':' + tour.id + ':v' + tour.version;

    function remember() {
        try {
            window.localStorage.setItem(storageKey, new Date().toISOString());
        } catch (e) {
            // Private window or blocked storage: the tour simply offers itself again next time.
        }
    }

    function alreadySeen() {
        try {
            return window.localStorage.getItem(storageKey) !== null;
        } catch (e) {
            return true; // without storage it would pop up on every visit: do not auto-start
        }
    }

    function visible(element) {
        if (!element || element.getClientRects().length === 0) {
            return false;
        }
        const style = window.getComputedStyle(element);

        return style.visibility !== 'hidden' && style.display !== 'none';
    }

    function find(target) {
        const all = document.querySelectorAll('[data-tour="' + window.CSS.escape(target) + '"]');

        return Array.prototype.find.call(all, visible) || null;
    }

    function steps() {
        const list = [];
        tour.steps.forEach(function (step) {
            const element = step.target ? find(step.target) : null;
            if (step.target && !element) {
                return;
            }
            list.push({
                element: element || undefined,
                popover: {
                    title: step.title,
                    description: step.description,
                    side: step.side,
                    align: 'start',
                },
            });
        });

        return list;
    }

    let active = null;

    function start() {
        if (active && active.isActive()) {
            return;
        }
        const list = steps();
        if (list.length === 0) {
            return;
        }

        active = factory({
            steps: list,
            showProgress: true,
            progressText: tour.ui.progress,
            nextBtnText: tour.ui.next,
            prevBtnText: tour.ui.prev,
            doneBtnText: tour.ui.done,
            allowClose: true,
            allowKeyboardControl: true,
            smoothScroll: true,
            stagePadding: 6,
            stageRadius: 8,
            overlayOpacity: 0.55,
            popoverClass: 'admin-tour-popover',
            onPopoverRender: function (popover, options) {
                if (options.driver.isLastStep()) {
                    return;
                }
                const skip = document.createElement('button');
                skip.type = 'button';
                skip.className = 'admin-tour-skip';
                skip.textContent = tour.ui.skip;
                skip.addEventListener('click', function () {
                    options.driver.destroy();
                });
                popover.footerButtons.prepend(skip);
            },
        });
        active.drive();
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-admin-tour-start]');
        if (trigger) {
            event.preventDefault();
            start();
        }
    });

    if (!alreadySeen()) {
        // After layout settles (sidebar, charts), so the highlighted boxes are where they end up.
        window.addEventListener('load', function () {
            window.setTimeout(function () {
                // Marked when shown, not when closed: driver.js 1.8 does not call onDestroyed from
                // its Done button, and a reload halfway through should not start it over either.
                remember();
                start();
            }, 500);
        });
    }
})();
