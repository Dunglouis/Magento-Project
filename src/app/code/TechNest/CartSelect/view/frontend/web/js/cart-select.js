/**
 * Cart page: a tick box on every cart line and a "Select all" box above the table. Each change is saved
 * at once (technest_cartselect/cart/select), then the summary totals and shipping rates are reloaded.
 * The "Proceed to Checkout" buttons show how many lines are ticked and are disabled when none is.
 */
define([
    'jquery',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Checkout/js/action/recollect-shipping-rates',
    'Magento_Customer/js/customer-data',
    'mage/cookies'
], function ($, getTotals, recollectShippingRates, customerData) {
    'use strict';

    return function (config, element) {
        var $table = $(element),
            $all = $('<input type="checkbox" class="tn-select__box" id="tn-select-all">'),
            $bar = $('<div class="tn-select-all"></div>'),
            $checkout = $('.cart-summary .action.primary.checkout, .cart.main.actions .action.primary.checkout'),
            busy = false;

        /**
         * Cart line id from the line's qty input name: cart[<id>][qty]
         *
         * @param {jQuery} $line tbody.cart.item
         * @return {String|null}
         */
        function lineId($line) {
            var match = ($line.find('input[name$="[qty]"]').attr('name') || '').match(/^cart\[(\d+)]/);

            return match ? match[1] : null;
        }

        function boxes() {
            return $table.find('.tn-select__box[data-item-id]');
        }

        function refresh() {
            var total = boxes().length,
                ticked = boxes().filter(':checked').length;

            $all.prop({checked: total > 0 && ticked === total, indeterminate: ticked > 0 && ticked < total});
            $bar.find('.tn-select-all__count').text('(' + ticked + '/' + total + ')');
            boxes().each(function () {
                $(this).closest('tbody.cart.item').toggleClass('tn-unselected', !this.checked);
            });
            $checkout.each(function () {
                var $button = $(this),
                    $label = $button.find('span').first();

                if (!$button.data('tnLabel')) {
                    $button.data('tnLabel', $label.text());
                }
                $label.text($button.data('tnLabel') + ' (' + ticked + ')');
                $button.prop('disabled', ticked === 0).toggleClass('disabled', ticked === 0);
            });
        }

        /**
         * @param {Object} data item_ids[] or all, selected
         * @param {Function} undo restores the boxes when saving fails
         */
        function save(data, undo) {
            busy = true;
            $table.addClass('tn-select-busy');
            $.ajax({
                url: config.selectUrl,
                type: 'POST',
                dataType: 'json',
                data: $.extend({'form_key': $.mage.cookies.get('form_key')}, data)
            }).done(function () {
                getTotals([]);
                recollectShippingRates();
                customerData.reload(['cart'], true);
            }).fail(function () {
                undo();
                refresh();
                window.alert(config.errorMessage);
            }).always(function () {
                busy = false;
                $table.removeClass('tn-select-busy');
            });
        }

        $table.find('tbody.cart.item').each(function () {
            var $line = $(this),
                id = lineId($line),
                $box;

            if (!id) {
                return;
            }
            $box = $('<input type="checkbox" class="tn-select__box">')
                .attr('data-item-id', id)
                .attr('aria-label', config.selectItemLabel.replace('%1', $line.find('.product-item-name').text().trim()))
                .prop('checked', config.selected[id] !== false);
            $line.find('td.col.item').first().prepend($('<span class="tn-select"></span>').append($box));
        });

        $bar.append($('<label class="tn-select" for="tn-select-all"></label>')
            .append($all)
            .append($('<span></span>').text(config.selectAllLabel))
            .append(' <span class="tn-select-all__count"></span>'));
        $table.closest('.cart.table-wrapper').before($bar);

        $table.on('change', '.tn-select__box[data-item-id]', function () {
            var box = this;

            if (busy) {
                box.checked = !box.checked;
                return;
            }
            refresh();
            save({'item_ids': [$(box).data('item-id')], selected: box.checked ? 1 : 0}, function () {
                box.checked = !box.checked;
            });
        });

        $all.on('change', function () {
            var checked = this.checked,
                before = boxes().map(function () {
                    return this.checked;
                }).get();

            if (busy) {
                this.checked = !checked;
                return;
            }
            boxes().prop('checked', checked);
            refresh();
            save({all: 1, selected: checked ? 1 : 0}, function () {
                boxes().each(function (i) {
                    this.checked = before[i];
                });
            });
        });

        refresh();
    };
});
