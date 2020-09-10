<?php

namespace Swarming\SubscribePro\Model\ApplePay;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Swarming\SubscribePro\Api\Data\ApplePayAuthDataInterface;
use Swarming\SubscribePro\Api\Data\ApplePayAuthDataInterfaceFactory;
use Swarming\SubscribePro\Api\ApplePayAuthInterface;

class Auth implements ApplePayAuthInterface
{
    /**
     * @var ApplePayAuthDataInterfaceFactory $authData
     */
    private $authData;

    /**
     * @var Ui\ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var UrlInterface $url
     */
    private $url;

    /**
     * @var CustomerSession $customerSession
     */
    private $customerSession;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * Auth constructor
     *
     * @param ApplePayAuthDataInterfaceFactory $authData
     * @param Ui\ConfigProvider $configProvider
     * @param UrlInterface $url
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManagerInterface
     */
    public function __construct(
        ApplePayAuthDataInterfaceFactory $authData,
        Ui\ConfigProvider $configProvider,
        UrlInterface $url,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManagerInterface
    ) {
        $this->authData = $authData;
        $this->configProvider = $configProvider;
        $this->url = $url;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManagerInterface;
    }

    /**
     * @inheritdoc
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function get(): ApplePayAuthDataInterface
    {
        /** @var ApplePayAuthDataInterface $data */
        $data = $this->authData->create();
        $data->setClientToken($this->getClientToken());
        $data->setDisplayName($this->getDisplayName());
        $data->setActionSuccess($this->getActionSuccess());
        $data->setIsLoggedIn($this->isLoggedIn());
        $data->setStoreCode($this->getStoreCode());

        return $data;
    }

    /**
     * @return string|null
     * @throws InputException
     * @throws NoSuchEntityException
     */
    protected function getClientToken()
    {
        return $this->configProvider->getClientToken();
    }

    /**
     * @return string|null
     */
    protected function getDisplayName()
    {
        return $this->configProvider->getMerchantName();
    }

    /**
     * @return string
     */
    protected function getActionSuccess(): string
    {
        return $this->url->getUrl('checkout/onepage/success', ['_secure' => true]);
    }

    /**
     * @return bool
     */
    protected function isLoggedIn(): bool
    {
        return (bool) $this->customerSession->isLoggedIn();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getStoreCode(): string
    {
        return $this->storeManager->getStore()->getCode();
    }
}
