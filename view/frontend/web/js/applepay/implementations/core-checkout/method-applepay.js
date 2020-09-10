/*browser:true*/
/*global define*/
define(
    ['uiComponent', 'Magento_Checkout/js/model/payment/renderer-list'],
    function (Component, rendererList) {
        'use strict';
console.log('adding apple pay method renderer');
        rendererList.push(
            {
                type: 'subscribe_pro_applepay',
                component: 'Swarming_SubscribePro/js/applepay/implementations/core-checkout/method-renderer/applepay'
            }
        );

        return Component.extend({});
    }
);
