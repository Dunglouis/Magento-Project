/**
 * Header search autocomplete: product rows (image, name, price) next to the search terms.
 * Choosing a product row opens the product page; choosing a term still searches for it.
 */
define(['jquery'], function ($) {
    'use strict';

    var productRow =
            '<li class="<%- data.row_class %>" id="qs-option-<%- data.index %>" role="option"' +
            ' data-url="<%- data.url %>" data-heading="<%- data.heading %>">' +
            '<img class="tn-qs-image" src="<%- data.image %>" alt="" loading="lazy" width="48" height="48">' +
            '<span class="qs-option-name"><%- data.title %></span>' +
            '<span class="tn-qs-price"><%- data.price %></span>' +
            '</li>';

    return function (quickSearch) {
        $.widget('mage.quickSearch', quickSearch, {
            options: {
                template:
                    '<% if (data.type === "product") { %>' + productRow + '<% } else { %>' +
                    quickSearch.prototype.options.template +
                    '<% } %>'
            },

            /**
             * Go straight to the product page when a product row is chosen
             *
             * @param {Event} e
             * @return {*}
             */
            _onSubmit: function (e) {
                var row = this.responseList.selected ? this.responseList.selected.closest('li') : $(),
                    url = row.data('url');

                if (url) {
                    e.preventDefault();
                    window.location.href = url;

                    return false;
                }

                return this._super(e);
            }
        });

        return $.mage.quickSearch;
    };
});
