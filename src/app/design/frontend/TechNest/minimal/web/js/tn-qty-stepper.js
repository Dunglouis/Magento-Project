/**
 * Cart page: "−" and "+" buttons around each quantity input. Changes are saved with the core
 * "Update Shopping Cart" button after a short pause, so several clicks make one request.
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $form = $(element).closest('form'),
            timer;

        function save() {
            clearTimeout(timer);
            timer = setTimeout(function () {
                $form.find('.action.update').trigger('click');
            }, 600);
        }

        $(element).find('input.qty').each(function () {
            var $input = $(this),
                $minus = $('<button type="button" class="tn-qty-btn" data-step="-1">−</button>'),
                $plus = $('<button type="button" class="tn-qty-btn" data-step="1">+</button>');

            $minus.attr('aria-label', config.decreaseLabel);
            $plus.attr('aria-label', config.increaseLabel);
            $input.wrap('<span class="tn-qty-stepper"></span>').before($minus).after($plus);
        });

        $(element).on('click', '.tn-qty-btn', function () {
            var $input = $(this).siblings('input.qty'),
                value = (parseInt($input.val(), 10) || 1) + parseInt($(this).data('step'), 10);

            if (value < 1) {
                return;
            }
            $input.val(value).trigger('change');
            save();
        });
    };
});
