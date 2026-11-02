/**
 * Recalculates the instalment plans when the product price box changes (configurable options, custom options).
 * Same formula as TechNest\Installment\Model\Calculator: total = price + price * rate per month * months.
 */
define([
    'jquery',
    'Magento_Catalog/js/price-utils'
], function ($, priceUtils) {
    'use strict';

    return function (config, element) {
        var $block = $(element),
            $priceBox = $('.product-info-main [data-role=priceBox][data-product-id="' + config.productId + '"]'),
            precision = parseInt(config.priceFormat.precision, 10) || 0,
            factor = Math.pow(10, precision);

        function round(amount) {
            return Math.round(amount * factor) / factor;
        }

        function format(amount) {
            return priceUtils.formatPriceLocale(amount, config.priceFormat, false);
        }

        function render(price) {
            var lowest = null;

            if (!(price > 0) || price < config.minPrice) {
                $block.prop('hidden', true);

                return;
            }

            $.each(config.rates, function (months, rate) {
                var total = round(price * (1 + rate / 100 * months)),
                    monthly = round(total / months),
                    $row = $block.find('tr[data-months="' + months + '"]');

                $row.find('[data-role=monthly]').text(format(monthly));
                $row.find('[data-role=total]').text(format(total));
                lowest = lowest === null ? monthly : Math.min(lowest, monthly);
            });

            $block.find('[data-role=lowest]').text(format(lowest));
            $block.prop('hidden', false);
        }

        $priceBox.on('priceUpdated', function (event, prices) {
            if (prices && prices.finalPrice) {
                render(parseFloat(prices.finalPrice.amount));
            }
        });
    };
});
