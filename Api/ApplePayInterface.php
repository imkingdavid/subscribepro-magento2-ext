<?php

namespace Swarming\SubscribePro\Api;

interface ApplePayInterface
{
    public function onShippingContactSelected();
    public function onShippingMethodSelected();
    public function onPaymentAuthorized();
}
