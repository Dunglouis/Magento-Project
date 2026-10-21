/**
 * Adds "tn-scrolled" to <body> once the page is scrolled, so the sticky header can show its shadow.
 */
define([], function () {
    'use strict';

    var ticking = false;

    function update() {
        document.body.classList.toggle('tn-scrolled', window.scrollY > 8);
        ticking = false;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    }, {passive: true});

    update();
});
