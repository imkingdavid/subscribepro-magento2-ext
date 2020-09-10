<?php

namespace Swarming\SubscribePro\Api;

interface ApplePayInterface
{
    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function onShippingContactSelected();

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function onShippingMethodSelected();

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function onPaymentAuthorized();
}
