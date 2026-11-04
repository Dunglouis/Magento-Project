/**
 * "Quick view" button on each product card of the grid; opens a modal with the AJAX content
 * of /quickview/product/view (TechNest\QuickView\Controller\Product\View).
 */
define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function ($, modal) {
    'use strict';

    var CARDS = '.page-products .products-grid .product-item-info, .page-products .products-list .product-item-info';

    return function (config) {
        var $content = $('<div class="tn-quickview" aria-live="polite"></div>'),
            request = null;

        modal({
            type: 'popup',
            modalClass: 'tn-quickview-modal',
            buttons: [],
            closed: function () {
                if (request) {
                    request.abort();
                }
                $content.empty();
            }
        }, $content);

        function addButtons() {
            $(CARDS).each(function () {
                var $card = $(this),
                    id = $card.find('[data-role=priceBox][data-product-id]').first().data('product-id'),
                    name = $.trim($card.find('.product-item-link').first().text());

                if (!id || $card.find('[data-role=tn-quickview]').length) {
                    return;
                }
                $('<button type="button" class="tn-quickview-trigger" data-role="tn-quickview"></button>')
                    .attr('data-product-id', id)
                    .attr('aria-label', config.ariaLabel.replace('%1', name))
                    .text(config.label)
                    .appendTo($card);
            });
        }

        function open(id) {
            $content.html($('<p class="tn-quickview__loading"></p>').text(config.loading));
            $content.modal('openModal');
            request = $.ajax({url: config.url, data: {id: id}, dataType: 'html', cache: false})
                .done(function (html) {
                    $content.html(html).trigger('contentUpdated');
                })
                .fail(function (xhr, status) {
                    if (status !== 'abort') {
                        $content.html($('<p class="message error"></p>').text(config.error));
                    }
                })
                .always(function () {
                    request = null;
                });
        }

        $(document).on('click', '[data-role=tn-quickview]', function (event) {
            event.preventDefault();
            open($(this).data('product-id'));
        });

        addButtons();
        $(document).on('contentUpdated', addButtons);
    };
});
