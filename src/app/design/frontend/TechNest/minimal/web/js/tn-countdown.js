/**
 * Live countdowns: every [data-tn-countdown="<unix time>"] gets its [data-part] days/hours/minutes/seconds
 * updated each second. When the time runs out the closest [data-tn-countdown-scope] (the home flash sale
 * section) is hidden, or just the countdown itself on product cards.
 */
define(['domReady!'], function () {
    'use strict';

    var timers = Array.prototype.slice.call(document.querySelectorAll('[data-tn-countdown]')),
        pad = function (value) {
            return value < 10 ? '0' + value : String(value);
        };

    /**
     * @param {HTMLElement} element
     * @return {Boolean} whether the countdown is still running
     */
    function render(element) {
        var left = Math.max(0, parseInt(element.getAttribute('data-tn-countdown'), 10) * 1000 - Date.now()),
            seconds = Math.floor(left / 1000),
            parts = {
                days: String(Math.floor(seconds / 86400)),
                hours: pad(Math.floor(seconds % 86400 / 3600)),
                minutes: pad(Math.floor(seconds % 3600 / 60)),
                seconds: pad(seconds % 60)
            };

        Object.keys(parts).forEach(function (name) {
            var part = element.querySelector('[data-part="' + name + '"]');

            if (part && part.textContent !== parts[name]) {
                part.textContent = parts[name];
            }
        });

        if (left === 0) {
            (element.closest('[data-tn-countdown-scope]') || element).hidden = true;
        }

        return left > 0;
    }

    function tick() {
        timers = timers.filter(render);

        if (timers.length) {
            window.setTimeout(tick, 1000 - Date.now() % 1000);
        }
    }

    tick();
});
