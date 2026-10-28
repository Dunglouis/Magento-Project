/**
 * Home hero slider ([data-tn-hero], CMS block "technest-hero"): a scroll-snap track that can be swiped,
 * plus dots and an autoplay that moves on every 6 s. Autoplay pauses while the pointer or focus is inside,
 * and is off when the user prefers reduced motion (dots and swiping still work).
 */
define(['mage/translate', 'domReady!'], function ($t) {
    'use strict';

    var DELAY = 6000,
        reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * @param {HTMLElement} hero
     */
    function init(hero) {
        var track = hero.querySelector('.tn-hero__track'),
            slides = Array.prototype.slice.call(hero.querySelectorAll('.tn-hero__slide')),
            dots = document.createElement('div'),
            current = 0,
            paused = false,
            timer;

        if (!track || slides.length < 2) {
            return;
        }

        function show(index, smooth) {
            current = (index + slides.length) % slides.length;
            track.scrollTo({
                left: slides[current].offsetLeft - slides[0].offsetLeft,
                behavior: smooth && !reducedMotion ? 'smooth' : 'auto'
            });
        }

        function markActive() {
            Array.prototype.forEach.call(dots.children, function (dot, i) {
                dot.setAttribute('aria-current', i === current ? 'true' : 'false');
            });
        }

        function schedule() {
            window.clearTimeout(timer);

            if (!reducedMotion) {
                timer = window.setTimeout(function () {
                    if (!paused && !document.hidden) {
                        show(current + 1, true);
                    }
                    schedule();
                }, DELAY);
            }
        }

        dots.className = 'tn-hero__dots';
        slides.forEach(function (slide, i) {
            var dot = document.createElement('button');

            dot.type = 'button';
            dot.className = 'tn-hero__dot';
            dot.setAttribute('aria-label', $t('Go to slide %1').replace('%1', i + 1));
            dot.addEventListener('click', function () {
                show(i, true);
                schedule();
            });
            dots.appendChild(dot);
        });
        hero.appendChild(dots);

        // The slide nearest to the track's left edge is the current one (also after a swipe)
        track.addEventListener('scroll', function () {
            var step = slides[1].offsetLeft - slides[0].offsetLeft,
                nearest = Math.round(track.scrollLeft / step);

            if (nearest !== current && slides[nearest]) {
                current = nearest;
                markActive();
            }
        }, {passive: true});

        ['mouseenter', 'focusin', 'touchstart'].forEach(function (name) {
            hero.addEventListener(name, function () {
                paused = true;
            }, {passive: true});
        });
        ['mouseleave', 'focusout'].forEach(function (name) {
            hero.addEventListener(name, function () {
                paused = false;
            });
        });

        markActive();
        schedule();
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-tn-hero]'), init);
});
