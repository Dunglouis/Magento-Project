/**
 * Signing in from the checkout sign-in popup goes on to checkout (core just reloads the page).
 */
define(['jquery', 'mage/utils/wrapper', 'mage/url'], function ($, wrapper, url) {
    'use strict';

    return function (loginAction) {
        var wrapped = wrapper.wrap(loginAction, function (original, loginData, redirectUrl, isGlobal, messages) {
            if (!redirectUrl && $('.popup-authentication._show').length) {
                redirectUrl = url.build('checkout');
            }
            return original(loginData, redirectUrl, isGlobal, messages);
        });

        wrapped.registerLoginCallback = loginAction.registerLoginCallback;
        return wrapped;
    };
});
