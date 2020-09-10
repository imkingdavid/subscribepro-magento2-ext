<?php
namespace Swarming\SubscribePro\Model\ApplePay\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Asset\Repository;
use Swarming\SubscribePro\Model\ApplePay\Config;
use Swarming\SubscribePro\Platform\Manager\Customer;
use Swarming\SubscribePro\Platform\Tool\Oauth;

class ConfigProvider implements ConfigProviderInterface
{
    const METHOD_CODE = 'subscribe_pro_applepay';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Oauth
     */
    private $oauthTool;

    /**
     * @var Customer
     */
    private $platformCustomerManager;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var Repository
     */
    private $assetRepo;

    /**
     * @var string
     */
    private $clientToken = '';

    /**
     * ConfigProvider constructor.
     * @param Config $config
     * @param Oauth $oauthTool
     * @param Customer $platformCustomerManager
     * @param Session $customerSession
     * @param Repository $assetRepo
     */
    public function __construct(
        Config $config,
        Oauth $oauthTool,
        Customer $platformCustomerManager,
        Session $customerSession,
        Repository $assetRepo
    ) {
        $this->config = $config;
        $this->oauthTool = $oauthTool;
        $this->platformCustomerManager = $platformCustomerManager;
        $this->customerSession = $customerSession;
        $this->assetRepo = $assetRepo;
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        return [
            'payment' => [
                'subscribe_pro_applepay' => [
                    'clientToken' => $this->getClientToken(),
                    'merchantName' => $this->getMerchantName(),
                    'merchantDomainName' => $this->getMerchantDomainName(),
                    'paymentMarkSrc' => $this->getPaymentMarkSrc()
                ]
            ]
        ];
    }

    public function getClientToken()
    {
        if (!empty($this->clientToken)) {
            return $this->clientToken;
        }

        $this->clientToken = $this->oauthTool->getWidgetAccessTokenByCustomerId($this->getPlatformCustomerId());
    }

    public function getMerchantDomainname(): string
    {
        return $this->config->getMerchantDomainName();
    }

    /**
     * Get merchant name
     *
     * @return string
     */
    public function getMerchantName(): string
    {
        return $this->config->getMerchantName();
    }

    /**
     * Get the url to the payment mark image
     * @return mixed
     */
    public function getPaymentMarkSrc()
    {
        return $this->assetRepo->getUrl('PayPal_Braintree::images/applepaymark.png');
    }

    protected function getPlatformCustomerId()
    {
        return $this->platformCustomerManager->getCustomerById(
            $this->customerSession->getCustomerId(),
            false,
            $this->customerSession->getCustomer()->getWebsiteId()
        )->getId();
    }
}
