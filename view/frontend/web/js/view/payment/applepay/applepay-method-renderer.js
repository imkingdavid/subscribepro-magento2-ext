/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'Swarming_SubscribePro/js/view/payment/applepay/applepay-button'
    ],
    function ($, Component, quote, button) {
        'use strict';

        return Component.extend(CcForm).extend({
            defaults: {
                template: 'Swarming_SubscribePro/payment/applepay',
                paymentMethodNonce: null,
                grandTotalAmount: 0,
                deviceSupported: button.deviceSupported()
            },

            getApplePayButton: function(id) {
                button.init(
                    document.getElementById(id),
                    this
                );
            },

            initObservable: function () {
                this._super()
                this.grandTotalAmount = parseFloat(quote.totals()['base_grand_total']).toFixed(2);

                quote.totals.subscribe(function () {
                    if (this.grandTotalAmount !== quote.totals()['base_grand_total']) {
                        this.grandTotalAmount = parseFloat(quote.totals()['base_grand_total']).toFixed(2);
                    }
                }.bind(this));

                return this;
            },

            /**
             * Apple pay place order method
             */
            startPlaceOrder: function (nonce, event, session) {
                this.setPaymentMethodNonce(nonce);
                this.placeOrder();

                session.completePayment(ApplePaySession.STATUS_SUCCESS);
            },

            /**
             * Save nonce
             */
            setPaymentMethodNonce: function (nonce) {
                this.paymentMethodNonce = nonce;
            },

            /**
             * Retrieve the client token
             * @returns null|string
             */
            getClientToken: function () {
                return window.checkoutConfig.payment[this.getCode()].clientToken;
            },

            /**
             * Payment request data
             */
            getPaymentRequest: function () {
                return {
                    total: {
                        label: this.getDisplayName(),
                        amount: this.grandTotalAmount
                    }
                };
            },

            /**
             * Merchant display name
             */
            getDisplayName: function () {
                return window.checkoutConfig.payment[this.getCode()].merchantName;
            },/**
             * Get data
             * @returns {Object}
             */
            getData: function () {
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'payment_method_nonce': this.paymentMethodNonce
                    }
                };
                return data;
            },

            /**
             * Return image url for the apple pay mark
             */
            getPaymentMarkSrc: function () {
                return window.checkoutConfig.payment[this.getCode()].paymentMarkSrc;
            }
        });
    }
);
