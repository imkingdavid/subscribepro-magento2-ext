
define(
    [
        'jquery',
        'underscore',
        'Swarming_SubscribePro/js/model/payment/config',
        'Swarming_SubscribePro/js/model/payment/spreedly',
        'mage/translate'
    ],
    function(
        $,
        _,
        config,
        spreedly,
        $t
    ) {
        'use strict';

        return {
            defaults: {
                isCustomerLoggedIn: false,
                cartHasProductsToCreateNewSubscription: false,
                onshippingcontactselectedUrl: '',
                onshippingmethodselectedUrl: '',
                onpaymentauthorizedUrl: '',
                createSessionUrl: '',
                merchantDomainName: '',
                merchantDisplayName: '',
                apiAccessToken: '',
                paymentRequest: {}
            },

            initialize: function () {
                this._super();
                this.showApplePayButtons();
            },

            showApplePayButtons: function() {
                if (!this.customerLoggedIn || !this.config.cartHasProductsToCreateNewSubscription || !window.ApplePaySession) {
                    return;
                }

                if (window.ApplePaySession.canMakePayments) {
                    $('.sp-apple-pay-button-container').click(this.onApplePayButtonClicked.bind(this)).show();
                }
            },

            onApplePayButtonClicked: function() {
                var self = this;

                const paymentRequest = this.paymentRequest;
                paymentRequest.total = self.replaceTotalLabel(paymentRequest.total, self.displayName);

                const session = new ApplePaySession(1, paymentRequest);

                session.onvalidatemerchant = function (event) {
                    $.ajax({
                        url: self.createSessionUrl,
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json; charset=utf-8',
                        crossDomain: true,
                        headers: {
                            'Authorization': 'Bearer ' + self.config.apiAccessToken
                        },
                        data: JSON.stringify({
                            url: event.validationURL,
                            merchantDomainName: self.config.merchantDomainName
                        }),
                        success: function (data, textStatus, jqXHR) {
                           self.merchantDisplayName = data.displayName;
                           session.completeMerchantValidation(data);
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            console.log(errorThrown);
                            session.abort();
                        }
                    });
                };

                session.onshippingcontactselected = function (event) {
                    $.ajax({
                        url: self.onshippingcontactselectedUrl,
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json; charset=utf-8',
                        data: JSON.stringify({
                            shippingContact: event.shippingContact
                        }),
                        success: function (data, textStatus, jqXHR) {
                            session.completeShippingContactSelection(
                                ApplePaySession.STATUS_SUCCESS,
                                data.newShippingMethods,
                                self.replaceTotalLabel(data.newTotal, self.displayName),
                                data.newLineItems
                            );
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.log(errorThrown);
                            session.abort();
                        }
                    })
                }

                session.onshippingmethodselected = function (event) {
                    $.ajax({
                        url: self.onshippingmethodselectedUrl,
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json; charset=utf-8',
                        data: JSON.stringify({
                            shippingMethod: event.shippingMethod
                        }),
                        success: function (data, textStatus, jqXHR) {
                            session.completeShippingMethodSelection(
                                ApplePaySession.STATUS_SUCCESS,
                                self.replaceTotalLabel(data.newTotal, self.displayName),
                                data.newLineItems
                            );
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            console.log(errorThrown);
                            session.abort();
                        }
                    });
                };

                session.onpaymentauthorized = function (event) {
                    $.ajax({
                        url: self.onpaymentauthorizedUrl,
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json; charset=utf-8',
                        data: JSON.stringify({
                            payment: event.payment
                        }),
                        success: function (data, textStatus, jqXHR) {
                            session.completePayment(ApplePaySession.STATUS_SUCCESS);
                            window.location.href = data.redirectUrl;
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            console.log(errorThrown);
                            session.abort();
                        }
                    });
                };

                session.begin();
            },

            replaceTotalLabel: function (total, label) {
                let newTotal = {
                    label: label,
                    amount: total.amount
                };
                if (total.type) {
                    newTotal.type = total.type;
                }

                return newTotal;
            },


            initSpreedly: function () {
                spreedly.init(
                    $.proxy(this.onFieldEvent, this),
                    $.proxy(this.onPaymentMethod, this),
                    $.proxy(this.validationPaymentData, this),
                    $.proxy(this.onErrors, this)
                );
            },

            onFieldEvent: function (name, event, activeElement, inputData) {
                var hostedField = hostedFieldValidator(name, event, inputData);
                if (hostedField.isValid !== undefined) {
                    this.isValidHostedFields = hostedField.isValid;
                }
                if (hostedField.cardType !== undefined) {
                    this.selectedCardType(hostedField.cardType);
                }
                this.updateSaveActionAllowed();
            },

            validationCreditCardExpMonth: function (isFocused) {
                this.isValidExpDate = expirationFieldValidator(
                    isFocused,
                    'month',
                    this.creditCardExpMonth(),
                    this.creditCardExpYear()
                );
                this.updateSaveActionAllowed();
            },

            validationCreditCardExpYear: function (isFocused) {
                this.isValidExpDate = expirationFieldValidator(
                    isFocused,
                    'year',
                    this.creditCardExpMonth(),
                    this.creditCardExpYear()
                );
                this.updateSaveActionAllowed();
            },

            startPlaceOrder: function () {
                if (this.isValidHostedFields && this.isValidExpDate) {
                    spreedly.validate();
                }
            },

            validationPaymentData: function (inputProperties) {
                if (inputProperties['validNumber'] && (inputProperties['validCvv'] || !config.hasVerification())) {
                    this.tokenizeCreditCard();
                }

                if (!inputProperties['validNumber']) {
                    hostedFields.addClass('number', 'invalid');
                }

                if (!inputProperties['validCvv'] && config.hasVerification()) {
                    hostedFields.addClass('cvv', 'invalid');
                }
            },

            tokenizeCreditCard: function () {
                spreedly.tokenizeCreditCard(this.getPaymentData());
            },

            getPaymentData: function () {
                return {};
            },

            onPaymentMethod: function (token) {
                this.paymentMethodToken(token);
                this.submitPayment();
            },

            submitPayment: function () {},

            onErrors: function (errors) {
                this.paymentMethodToken(null);

                for(var i = 0; i < errors.length; i++) {
                    if (errors[i]['attribute'] == 'number' || errors[i]['attribute'] == 'cvv') {
                        hostedFields.addClass(errors[i]['attribute'], 'invalid');
                    }
                    if (errors[i]['attribute'] == 'month' || errors[i]['attribute'] == 'year') {
                        expirationFields.addClass(errors[i]['attribute'], 'invalid');
                    }
                }
            },

            getIcons: function (type) {
                return config.getIcons().hasOwnProperty(type) ? config.getIcons()[type] : false;
            },

            getCcAvailableTypesValues: function () {
                return _.map(config.getAvailableCardTypes(), function (value, key) {
                    return {
                        'value': key,
                        'type': value
                    };
                });
            },

            hasVerification: function () {
                return config.hasVerification();
            },

            getCvvImageHtml: function () {
                return '<img src="' + config.getCvvImageUrl() +
                    '" alt="' + $t('Card Verification Number Visual Reference') +
                    '" title="' + $t('Card Verification Number Visual Reference') +
                    '" />';
            }
        };
    }
);
