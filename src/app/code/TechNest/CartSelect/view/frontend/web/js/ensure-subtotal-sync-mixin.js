/**
 * Magento 2.4.9 reloads the cart form when the summary subtotal differs from the sum of the cart lines.
 * With TechNest_CartSelect they differ on purpose (unticked lines are not counted), and the cart totals
 * are recollected on every tick change, so the check is switched off.
 */
define(['jquery'], function ($) {
    'use strict';

    return function (ensureSubtotalSync) {
        return function (config, element) {
            $('body').data('cart-synced', true);

            return ensureSubtotalSync(config, element);
        };
    };
});
