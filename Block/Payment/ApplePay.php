<?php

namespace Swarming\SubscribePro\Block\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\ConfigurableInfo;
use Magento\Payment\Gateway\ConfigInterface;
use Swarming\SubscribePro\Helper\Quote as QuoteHelper;

class ApplePay extends ConfigurableInfo
{
    /**
     * @var QuoteHelper
     */
    protected $quoteHelper;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @param Context $context
     * @param ConfigInterface $config
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param QuoteHelper $quoteHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigInterface $config,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        QuoteHelper $quoteHelper,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->quoteHelper = $quoteHelper;
        parent::__construct($context, $config, $data);
    }

    /**
     * @param string $field
     * @return Phrase
     */
    protected function getLabel($field)
    {
        return __($field);
    }

    /**
     * See whether or not the current quote
     *
     * @throws LocalizedException
     */
    protected function cartContainsSubscriptions()
    {
        $quote = null;
        try {
            if ($this->checkoutSession->hasQuote()) {
                $quote = $this->checkoutSession->getQuote();
            }
        } catch (NoSuchEntityException $e) {
            return false;
        }

        if (!$quote) {
            return false;
        }

        return $this->quoteHelper->hasSubscription($quote);
    }

    protected function isCustomerLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }
}
