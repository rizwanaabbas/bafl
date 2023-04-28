/**
 * Copyright © 2020 BAFL. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'bafl_card',
                component: 'Bafl_Payment/js/view/payment/method-renderer/bafl_card'
            },
            {
                type: 'bafl_wallet',
                component: 'Bafl_Payment/js/view/payment/method-renderer/bafl_wallet'
            },
            {
                type: 'bafl_account',
                component: 'Bafl_Payment/js/view/payment/method-renderer/bafl_account'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
