<?php

namespace Swarming\SubscribePro\Api;

use Swarming\SubscribePro\Api\Data\ApplePayAuthDataInterface;

/**
 * Interface AuthInterface
 * @api
 **/
interface ApplePayAuthInterface
{
    /**
     * Returns details required to be able to submit a payment with apple pay.
     * @return ApplePayAuthDataInterface
     */
    public function get(): ApplePayAuthDataInterface;
}
