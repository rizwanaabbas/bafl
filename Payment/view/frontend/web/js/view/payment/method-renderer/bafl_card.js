/**
 * Copyright © 2020 BAFL. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/redirect-on-success',
        'mage/url'
    ],
    function (Component, redirectOnSuccessAction, url) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Bafl_Payment/payment/card'
            },

            afterPlaceOrder: function () {
                redirectOnSuccessAction.redirectUrl = url.build('baflp/index/index');
                this.redirectAfterPlaceOrder = true;
            },
        });
    }
);