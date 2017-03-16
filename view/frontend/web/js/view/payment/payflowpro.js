/**
 * Payflowpro Magento JS component
 */
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,rendererList) {
        'use strict';
        rendererList.push({type: 'clarion_payflowpro',component: 'Clarion_Payflowpro/js/view/payment/method-renderer/clarion-payflowpro'});
        /** Add view logic here if needed */
        return Component.extend({});
    }
);