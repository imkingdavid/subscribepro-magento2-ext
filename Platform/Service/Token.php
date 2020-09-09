<?php

namespace Swarming\SubscribePro\Platform\Service;

use SubscribePro\Service\Token\TokenInterface;

/**
 * @method \SubscribePro\Service\Token\TokenService getService($websiteId = null)
 */
class Token extends AbstractService
{
    /**
     * @param array $tokenData
     * @param null $websiteId
     * @return TokenInterface
     */
    public function createToken(array $tokenData = [], $websiteId = null)
    {
        return $this->getService($websiteId)->createToken($tokenData);
    }

    /**
     * @param string $token
     * @param null $websiteId
     * @return TokenInterface
     */
    public function loadToken($token, $websiteId = null)
    {
        return $this->getService($websiteId)->loadToken($token);
    }

    /**
     * @param TokenInterface $token
     * @param null $websiteId
     * @return TokenInterface
     */
    public function saveToken(TokenInterface $token, $websiteId = null)
    {
        return $this->getService($websiteId)->saveToken($token);
    }
}
