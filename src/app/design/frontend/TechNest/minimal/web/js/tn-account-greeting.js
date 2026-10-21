/**
 * Fills the account menu toggle with "Welcome, <name>!" from customer data.
 * The pattern comes translated from the template (data-greeting); without a name the fallback text stays.
 */
define(['Magento_Customer/js/customer-data', 'domReady!'], function (customerData) {
    'use strict';

    var customer = customerData.get('customer');

    function update(data) {
        document.querySelectorAll('.customer-welcome .tn-greeting[data-greeting]').forEach(function (element) {
            if (data && data.fullname) {
                element.textContent = element.getAttribute('data-greeting').replace('%1', data.fullname);
            }
        });
    }

    customer.subscribe(update);
    update(customer());
});
