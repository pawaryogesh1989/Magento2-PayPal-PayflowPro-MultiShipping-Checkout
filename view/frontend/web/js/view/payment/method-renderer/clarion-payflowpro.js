/**
 * Payflowpro Magento JS component
 */
define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (Component, $) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Clarion_Payflowpro/payment/clarion-payflowpro'
                },
                getCode: function () {
                    return 'clarion_payflowpro';
                },
                isActive: function () {
                    return true;
                },
                validate: function () {
                    var $form = $('#' + this.getCode() + '-form');
                    return $form.validation() && $form.validation('isValid');
                },
                getData: function () {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'cc_number': this.creditCardNumber(),
                            'cc_type': this.creditCardType(),
                            'cc_cid': this.creditCardVerificationNumber(),
                            'cc_exp_year': this.creditCardExpYear(),
                            'cc_exp_month': this.creditCardExpMonth()
                        }
                    };
                }
            }
        );
    }
);