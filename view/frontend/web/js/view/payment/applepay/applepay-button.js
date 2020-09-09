/**
 * Braintree Apple Pay button
 **/
define(
    [
        'uiComponent',
        "knockout",
        "jquery",
        'Swarming_SubscribePro/js/model/payment/spreedly',
        'braintreeApplePay',
        'mage/translate'
    ],
    function (
        Component,
        ko,
        jQuery,
        spreedly,
        applePay,
        $t
    ) {
        'use strict';

        var that;

        return {
            init: function (element, context) {
                // No element or context
                if (!element || !context) {
                    return;
                }

                // Context must implement these methods
                if (typeof context.getClientToken !== 'function') {
                    console.error("Missing getClientToken method in ApplePay context", context);
                    return;
                }
                if (typeof context.getPaymentRequest !== 'function') {
                    console.error("Missing getPaymentRequest method in ApplePay context", context);
                    return;
                }
                if (typeof context.startPlaceOrder !== 'function') {
                    console.error("Missing startPlaceOrder method in ApplePay context", context);
                    return;
                }

                if (this.deviceSupported() === false) {
                    return;
                }

                console.log('Device supported, create Apple Pay session');

                // Create a button within the KO element, as apple pay can only be instantiated through
                // a valid on click event (ko onclick bind interferes with this).
                var el = document.createElement('div');
                el.className = "sp-apple-pay-button";
                el.title = $t("Pay with Apple Pay");
                el.alt = $t("Pay with Apple Pay");
                el.addEventListener('click', function (e) {
                    let applePaySession = new ApplePaySession(1, context.getPaymentRequest());
                    applePaySession.onvalidatemerchant = function (event) {
                        jQuery.ajax({
                            url: window.spApplePayConfig.createSessionUrl,
                            type: "POST",
                            dataType: "json",
                            contentType: "application/json; charset=utf-8",
                            crossDomain: true,
                            headers: {
                                'Authorization': 'Bearer ' + window.spApplePayConfig.accessToken
                            },
                            data: JSON.stringify({
                                url: event.validationURL,
                                merchantDomainName: window.spApplePayConfig.merchantDomainName
                            }),
                            success: function (data, textStatus, jqXHR) {
                                applePaySession.completeMerchantValidation(data);
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error('Apple Pay Error: ' + errorThrown);
                                applePaySession.abort();
                            }
                        })
                    };

                    applePaySession.onshippingcontactselected = function (event) {
                        jQuery.ajax({
                            url: window.spApplePayConfig.onShippingContactSelectedUrl,
                            type: "POST",
                            dataType: "json",
                            contentType: "application/json; charset=utf-8",
                            data: JSON.stringify({
                                shippingContact: event.shippingContact
                            }),
                            success: function (data, textStatus, jqXHR) {
                                applePaySession.completeShippingContactSelection(
                                    ApplePaySession.STATUS_SUCCESS,
                                    data.newShippingMethods,
                                    self.replaceTotalLabel(data.newTotal, window.spApplePayConfig.merchantDisplayName),
                                    data.newLineItems);
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error('Apple Pay Error: ' + errorThrown);
                                applePaySession.abort();
                            }
                        });
                    };

                    applePaySession.onshippingmethodselected = function (event) {
                        jQuery.ajax({
                            url: window.spApplePayConfig.onShippingMethodSelectedUrl,
                            type: "POST",
                            dataType: "json",
                            contentType: "application/json; charset=utf-8",
                            data: JSON.stringify({
                                shippingMethod: event.shippingMethod
                            }),
                            success: function (data, textStatus, jqXHR) {
                                session.completeShippingMethodSelection(
                                    ApplePaySession.STATUS_SUCCESS,
                                    self.replaceTotalLabel(data.newTotal, self.displayName),
                                    data.newLineItems);
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error('Apple Pay Error: ' + errorThrown);
                                applePaySession.abort();
                            }
                        });
                    };

                    applePaySession.onpaymentauthorized = function (event) {
                        jQuery.ajax({
                            url: window.spApplePayConfig.onPaymentAuthorizedUrl,
                            type: "POST",
                            dataType: "json",
                            contentType: "application/json; charset=utf-8",
                            data: JSON.stringify({
                                payment: event.payment
                            }),
                            success: function (data, textStatus, jqXHR) {
                                // Complete payment
                                session.completePayment(ApplePaySession.STATUS_SUCCESS);
                                // Redirect to success page
                                window.location.href = data.redirectUrl;
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error('Apple Pay Error: ' + errorThrown);
                                applePaySession.abort();
                            }
                        });
                    };

                    applePaySession.begin();
                });


                spreedly.init() .create({
                    authorization: context.getClientToken()
                }, function (clientErr, clientInstance) {
                    if (clientErr) {
                        console.error('Error creating client:', clientErr);
                        return;
                    }

                    applePay.create({
                        client: clientInstance
                    }, function (applePayErr, applePayInstance) {
                        // No instance
                        if (applePayErr) {
                            console.error('Braintree ApplePay Error creating applePayInstance:', applePayErr);
                            return;
                        }

                        // Create a button within the KO element, as apple pay can only be instantiated through
                        // a valid on click event (ko onclick bind interferes with this).
                        var el = document.createElement('div');
                        el.className = "braintree-apple-pay-button";
                        el.title = $t("Pay with Apple Pay");
                        el.alt = $t("Pay with Apple Pay");
                        el.addEventListener('click', function (e) {
                            e.preventDefault();

                            // Payment request object
                            var paymentRequest = applePayInstance.createPaymentRequest(context.getPaymentRequest());
                            if (!paymentRequest) {
                                alert($t("We're unable to take payments through Apple Pay at the moment. Please try an alternative payment method."));
                                console.error('Braintree ApplePay Unable to create paymentRequest', paymentRequest);
                                return;
                            }

                            // Show the loader
                            jQuery("body").loader('show');

                            // Init apple pay session
                            try {
                                var session = new ApplePaySession(1, paymentRequest);
                            } catch (err) {
                                jQuery("body").loader('hide');
                                console.error('Braintree ApplePay Unable to create ApplePaySession', err);
                                alert($t("We're unable to take payments through Apple Pay at the moment. Please try an alternative payment method."));
                                return false;
                            }

                            // Handle invalid merchant
                            session.onvalidatemerchant = function (event) {
                                applePayInstance.performValidation({
                                    validationURL: event.validationURL,
                                    displayName: context.getDisplayName()
                                }, function (validationErr, merchantSession) {
                                    if (validationErr) {
                                        session.abort();
                                        console.error('Braintree ApplePay Error validating merchant:', validationErr);
                                        alert($t("We're unable to take payments through Apple Pay at the moment. Please try an alternative payment method."));
                                        return;
                                    }

                                    session.completeMerchantValidation(merchantSession);
                                });
                            };

                            // Attach payment auth event
                            session.onpaymentauthorized = function (event) {
                                applePayInstance.tokenize({
                                    token: event.payment.token
                                }, function (tokenizeErr, payload) {
                                    if (tokenizeErr) {
                                        console.error('Error tokenizing Apple Pay:', tokenizeErr);
                                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                                        return;
                                    }

                                    // Pass the nonce back to the payment method
                                    context.startPlaceOrder(payload.nonce, event, session);
                                });
                            };

                            // Attach onShippingContactSelect method
                            if (typeof context.onShippingContactSelect === 'function') {
                                session.onshippingcontactselected = function (event) {
                                    return context.onShippingContactSelect(event, session);
                                };
                            }

                            // Attach onShippingMethodSelect method
                            if (typeof context.onShippingMethodSelect === 'function') {
                                session.onshippingmethodselected = function (event) {
                                    return context.onShippingMethodSelect(event, session);
                                };
                            }

                            // Hook
                            if (typeof context.onButtonClick === 'function') {
                                context.onButtonClick(session, this, e);
                            } else {
                                jQuery("body").loader('hide');
                                session.begin();
                            }
                        });
                        element.appendChild(el);
                    });
                });
            },

            /**
             * Check the site is using HTTPS & apple pay is supported on this device.
             * @return boolean
             */
            deviceSupported: function () {
                if (location.protocol != 'https:') {
                    console.warn("Apple Pay requires your checkout be served over HTTPS");
                    return false;
                }

                if ((window.ApplePaySession && ApplePaySession.canMakePayments()) !== true) {
                    console.warn("Apple Pay is not supported on this device/browser");
                    return false;
                }

                return true;
            },

            replaceTotalLabel: function (total, label) {
                var newTotal = {
                    label: label,
                    amount: total.amount
                };
                if (total.type) {
                    newTotal.type = total.type;
                }

                return newTotal;
            }
        };
    }
);
