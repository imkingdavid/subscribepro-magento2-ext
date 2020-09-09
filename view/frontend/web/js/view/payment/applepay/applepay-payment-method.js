/*browser:true*/
/*global define*/
define(
    ['uiComponent', 'Magento_Checkout/js/model/payment/renderer-list'],
    function (Component, rendererList) {
        'use strict';

        rendererList.push(
            {
                type: 'subscribe_pro_applepay',
                component: 'Swarming_SubscribePro/js/view/payment/applepay/applepay-method-renderer'
            }
        );

        return Component.extend({});
    }
);
