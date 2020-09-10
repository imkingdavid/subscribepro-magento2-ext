<?php

namespace Swarming\SubscribePro\Observer\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\Method\Free;
use Psr\Log\LoggerInterface;
use Swarming\SubscribePro\Gateway\Config\Config;
use Swarming\SubscribePro\Gateway\Config\ConfigProvider;
use Swarming\SubscribePro\Model\ApplePay\Ui\ConfigProvider as ApplePayConfigProvider;
use Swarming\SubscribePro\Helper\Quote;

class Availability implements ObserverInterface
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var Quote
     */
    protected $quoteHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Session $checkoutSession
     * @param Quote $quoteHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Session $checkoutSession,
        Quote $quoteHelper,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteHelper = $quoteHelper;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Payment\Model\Method\Adapter $methodInstance */
        $methodInstance = $observer->getData('method_instance');

        /** @var \Magento\Framework\DataObject $result */
        $result = $observer->getData('result');

        /** @var \Magento\Quote\Api\Data\CartInterface $quote */
        $quote = $observer->getData('quote');
        $quote = $quote ?: $this->checkoutSession->getQuote();
        if (!$quote) {
            return;
        }

        $methodCode = $methodInstance->getCode();
        $isAvailable = $result->getData('is_available');

        $this->logger->info('Original value: ' . $methodCode . ' is ' . ($isAvailable ? '' : 'not ') . 'available.');

        if ($isAvailable) {
            $isActiveNonSubscription = $methodInstance->getConfigData(Config::KEY_ACTIVE_NON_SUBSCRIPTION);

            // For a subscription order, we filter out all payment methods except the Subscribe Pro and (sometimes) free methods
            if ($this->quoteHelper->hasSubscription($quote)) {
                switch ($methodCode) {
                    case Free::PAYMENT_METHOD_FREE_CODE:
                        $isAvailable = $this->quoteHelper->isRecurringQuote($quote);
                        break;
                    case ConfigProvider::CODE:
                    case ApplePayConfigProvider::METHOD_CODE:
                        $isAvailable = true;
                        break;
                    default:
                        $isAvailable = false;
                        break;
                }
            } elseif (ConfigProvider::CODE == $methodCode && !$isActiveNonSubscription) {
                $isAvailable = false;
            }

            $this->logger->info($methodCode . ' is ' . ($isAvailable ? '' : 'not ') . 'available.');

            $result->setData('is_available', $isAvailable);
        }
    }
}
