/**
 * Fades sections in as they scroll into view. Elements already on screen are left alone (no flash),
 * and nothing is hidden when IntersectionObserver is missing or the user prefers reduced motion.
 */
define([], function () {
    'use strict';

    var selector = [
            '.tn-shelf-section',
            '.tn-catnav',
            '.page-products .products-grid .product-item',
            '.block.related',
            '.product.info.detailed',
            '.tn-prose > *'
        ].join(','),
        observer;

    if (!('IntersectionObserver' in window) ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('tn-visible');
                observer.unobserve(entry.target);
            }
        });
    }, {rootMargin: '0px 0px -40px 0px', threshold: 0.05});

    document.querySelectorAll(selector).forEach(function (element) {
        if (element.getBoundingClientRect().top < window.innerHeight) {
            return;
        }
        element.classList.add('tn-reveal');
        observer.observe(element);
    });
});
