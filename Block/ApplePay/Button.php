<?php

namespace Swarming\SubscribePro\Block\ApplePay;

use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\Data\ProductInterface;
use Swarming\SubscribePro\Api\Data\ProductInterface as PlatformProductInterface;
use SubscribePro\Exception\InvalidArgumentException;
use SubscribePro\Exception\HttpException;
use Swarming\SubscribePro\Platform\Tool\Oauth;

class Button extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Swarming\SubscribePro\Model\Config\General
     */
    protected $generalConfig;

    /**
     * @var \Swarming\SubscribePro\Model\Config\Platform
     */
    protected $platformApiConfig;

    /**
     * @var \Swarming\SubscribePro\Model\Config\ApplePay
     */
    protected $applePayConfig;

    /**
     * @var \Swarming\SubscribePro\Platform\Tool\Oauth
     */
    protected $oauthTool;

    /**
     * @var \Swarming\SubscribePro\Platform\Manager\Customer
     */
    protected $platformCustomerManager;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Swarming\SubscribePro\Helper\QuoteItem
     */
    protected $quoteItemHelper;

    /**
     * @var \Swarming\SubscribePro\Helper\Product
     */
    protected $productHelper;

    /**
     * @var \Swarming\SubscribePro\Api\Data\ProductInterface
     */
    protected $platformProduct;

    /**
     * @var bool
     */
    protected $canRender = false;

    /**
     * @var /Psr/Log/LoggerInterface
     */
    protected $logger;

    /**
     * @param \Magento\Catalog\Block\Product\Context $context
     * @param \Swarming\SubscribePro\Model\Config\General $generalConfig
     * @param \Swarming\SubscribePro\Model\Config\Platform $platformApiConfig
     * @param \Swarming\SubscribePro\Model\Config\ApplePay $applePayConfig
     * @param \Swarming\SubscribePro\Platform\Tool\Oauth $oauthTool
     * @param \Swarming\SubscribePro\Platform\Manager\Customer $platformCustomerManager
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Swarming\SubscribePro\Helper\QuoteItem $quoteItemHelper
     * @param \Swarming\SubscribePro\Helper\Product $productHelper
     * @param \Psr\Log\LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Swarming\SubscribePro\Model\Config\General $generalConfig,
        \Swarming\SubscribePro\Model\Config\Platform $platformApiConfig,
        \Swarming\SubscribePro\Model\Config\ApplePay $applePayConfig,
        \Swarming\SubscribePro\Platform\Tool\Oauth $oauthTool,
        \Swarming\SubscribePro\Platform\Manager\Customer $platformCustomerManager,
        \Magento\Customer\Model\Session $customerSession,
        \Swarming\SubscribePro\Helper\QuoteItem $quoteItemHelper,
        \Swarming\SubscribePro\Helper\Product $productHelper,
        \Psr\Log\LoggerInterface $logger,
        array $data = []
    ) {
        $this->generalConfig = $generalConfig;
        $this->platformApiConfig = $platformApiConfig;
        $this->applePayConfig = $applePayConfig;
        $this->oauthTool = $oauthTool;
        $this->platformCustomerManager = $platformCustomerManager;
        $this->customerSession = $customerSession;
        $this->quoteItemHelper = $quoteItemHelper;
        $this->productHelper = $productHelper;
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _beforeToHtml()
    {
        if (!$this->generalConfig->isEnabled()) {
            $this->canRender = false;
        } else {
            $this->initJsLayout();
            $this->canRender = true;
        }
        return parent::_beforeToHtml();
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        if ($this->canRender) {
            return parent::getTemplate();
        }
        return '';
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function initJsLayout()
    {
        try {
            $this->jsLayout = $this->generateJsLayout();
        } catch (NoSuchEntityException $e) {
            if ($this->_appState->getMode() === AppState::MODE_DEVELOPER) {
                throw $e;
            }
            $this->setTemplate('');
        }
    }

    public function getCreateSessionUrl()
    {
        return rtrim($this->platformApiConfig->getBaseUrl(), '/') . '/services/v2/vault/applepay/create-session.json';
    }

    public function getMerchantDomainName()
    {
        return $this->applePayConfig->getMerchantDomainName();
    }

    public function getMerchantDisplayName()
    {
        return $this->applePayConfig->getMerchantName();
    }

    public function getOnShippingContactSelectedUrl()
    {
        return $this->getUrl('swarming_subscribepro/applepay/onshippingcontactselected');
    }

    public function getOnShippingMethodSelectedUrl()
    {
        return $this->getUrl('swarming_subscribepro/applepay/onshippingmethodselected');
    }

    public function getOnPaymentAuthorizedUrl()
    {
        return $this->getUrl('swarming_subscribepro/applepay/onpaymentauthorized');
    }

    public function getCustomerAccessToken()
    {
        $accessToken = $this->oauthTool->getWidgetAccessTokenByCustomerId($this->getPlatformCustomerId());
        return $accessToken['access_token'];
    }

    public function getPlatformCustomerId()
    {
        return $this->platformCustomerManager->getCustomerById(
            $this->customerSession->getCustomerId(),
            false,
            $this->customerSession->getCustomer()->getWebsiteId()
        )->getId();
    }
}
