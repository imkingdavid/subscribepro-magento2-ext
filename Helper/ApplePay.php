<?php

namespace Swarming\SubscribePro\Helper;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Helper\Data as DirectoryDataHelper;
use Magento\Directory\Model\RegionFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteRepository;
use Swarming\SubscribePro\Gateway\Config\Config;
use Swarming\SubscribePro\Platform\Manager\Customer;

class ApplePay
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var DirectoryDataHelper
     */
    protected $directoryHelper;

    /**
     * @var RegionFactory
     */
    protected $regionFactory;

    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var Customer
     */
    protected $platformCustomerManager;

    /**
     * @var \Swarming\SubscribePro\Platform\Manager\Applepay
     */
    protected $platformApplePayManager;

    /**
     * @var Config
     */
    protected $spGatewayConfig;

    /**
     * ApplePay constructor.
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param DirectoryDataHelper $directoryHelper
     * @param RegionFactory $regionFactory
     * @param QuoteRepository $quoteRepository
     * @param QuoteManagement $quoteManagement
     * @param Customer $platformCustomerManager
     * @param \Swarming\SubscribePro\Platform\Manager\Applepay $platformApplePayManager
     * @param Config  $spGatewayConfig
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        DirectoryDataHelper $directoryHelper,
        RegionFactory $regionFactory,
        QuoteRepository $quoteRepository,
        QuoteManagement $quoteManagement,
        Customer $platformCustomerManager,
        \Swarming\SubscribePro\Platform\Manager\ApplePay $platformApplePayManager,
        Config  $spGatewayConfig
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->directoryHelper = $directoryHelper;
        $this->regionFactory = $regionFactory;
        $this->quoteRepository = $quoteRepository;
        $this->quoteManagement = $quoteManagement;
        $this->platformCustomerManager = $platformCustomerManager;
        $this->platformApplePayManager = $platformApplePayManager;
        $this->spGatewayConfig = $spGatewayConfig;
    }

    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    public function setApplePayShippingContactOnQuote(array $applePayShippingContact)
    {
        $quote = $this->getQuote();

        $countryId = isset($applePayShippingContact['countryCode']) ? $applePayShippingContact['countryCode'] : null;
        if (empty($countryId)) {
            $countryName = isset($applePayShippingContact['countryName']) ? $applePayShippingContact['countryName'] : null;
            if (!empty($countryName)) {
                $countryId = $this->getCountryCodeFromName($countryName);
            }
        } else {
            $countryId = strtoupper($countryId);
        }

        $region = $this->regionFactory->create()->loadByCode($applePayShippingContact['administrativeArea'], $countryId) ?:
            $this->regionFactory->create()->loadByName($applePayShippingContact['administrativeArea'], $countryId);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCountryId($countryId);
        $shippingAddress->setCity(isset($applePayShippingContact['locality']) ? $applePayShippingContact['locality'] : null);
        $shippingAddress->setPostcode(isset($applePayShippingContact['postalCode']) ? $applePayShippingContact['postalCode'] : null);
        $shippingAddress->collectShippingRates();
        if ($region) {
            $shippingAddress->setRegionId($region->getId());
            $shippingAddress->setRegionCode($region->getCode());
        }
        $quote->setShippingAddress($shippingAddress);
        $quote->collectTotals();
        $this->quoteRepository->save($quote);
    }

    public function setApplePayShippingMethodOnQuote(array $applePayShippingMethod)
    {
        if (!empty($applePayShippingMethod['identifier'])) {
            $quote = $this->getQuote();
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShippingMethod($applePayShippingMethod['identifier']);
            $quote->setShippingAddress($shippingAddress);
            $quote->collectTotals();
            $this->quoteRepository->save($quote);
        }
    }

    public function getApplePayShippingMethods()
    {
        $quote = $this->getQuote();
        $shippingAddress = $quote->getShippingAddress();

        $shippingRates = $shippingAddress->collectShippingRates()->getGroupedAllShippingRates();

        $rates = [];
        $currentRate = false;

        foreach ($shippingRates as $carrier => $groupRates) {
            foreach ($groupRates as $shippingRate) {
                if ($quote->getShippingAddress()->getShippingMethod() == $shippingRate->getCode()) {
                    $currentRate = $this->convertShippingRate($shippingRate);
                } else {
                    $rates[] = $this->convertShippingRate($shippingRate);
                }
            }
        }

        if ($currentRate) {
            array_unshift($rates, $currentRate);
        }

        return $rates;
    }

    protected function convertShippingRate(\Magento\Quote\Model\Quote\Address\Rate $rate)
    {
        $detail = $rate->getMethodTitle();
        if ($rate->getCarrierTitle() == $detail || $detail == 'Free') {
            $detail = '';
        }

        return [
            'label' => $rate->getCarrierTitle(),
            'amount' => (float) number_format($rate->getPrice(), 2),
            'detail' => $detail,
            'identifier' => $rate->getCode()
        ];
    }

    public function getCountryCodeFromName($name)
    {
        $countries = $this->directoryHelper->getCountryCollection()->toOptionArray();
        foreach ($countries as $country) {
            if (strtolower($country['label']) == strtolower($name)) {
                return $country['value'];
            }
        }

        return null;
    }

    public function setApplePayPaymentOnQuote(array $applePayPayment)
    {
        if (empty($applePayPayment['token']['paymentData']) || !is_array($applePayPayment['token']['paymentData'])) {
            return;
        }

        $quote = $this->getQuote();

        if ($this->customerSession->isLoggedIn()) {
            $quote->setCustomer($this->customerSession->getCustomer());
        } else {
            if (isset($applePayPayment['shippingContact']['emailAddress'])) {
                $quote->setCustomerEmail($applePayPayment['shippingContact']['emailAddress']);
            }
            if (isset($applePayPayment['shippingContact']['givenName'])) {
                $quote->setCustomerFirstname($applePayPayment['shippingContact']['givenName']);
            }
            if (isset($applePayPayment['shippingContact']['familyName'])) {
                $quote->setCustomerLastname($applePayPayment['shippingContact']['familyName']);
            }
        }
        // Billing address
        $quote->getBillingAddress()->addData($this->convertToMagentoAddress($applePayPayment['billingContact']));
        // Shipping address
        if (!$quote->isVirtual()) {
            $quote->getShippingAddress()->addData($this->convertToMagentoAddress($applePayPayment['shippingContact']));
        }

        // Payment details
        if ($this->customerSession->isLoggedIn()) {
            $this->createPaymentProfileForCustomer($applePayPayment);
        } else {
            $this->createPaymentToken($applePayPayment);
        }
    }

    public function getApplePayTotal()
    {
        return [
            'label' => 'MERCHANT',
            'amount' => number_format($this->getQuote()->getGrandTotal(), 2),
        ];
    }

    /**
     * @return array
     */
    public function getApplePayLineItems()
    {
        return [
            [
                'label' => 'SUBTOTAL',
                'amount' => number_format($this->getQuote()->getShippingAddress()->getSubtotalWithDiscount(), 2),
            ],
            [
                'label' => 'SHIPPING',
                'amount' => number_format($this->getQuote()->getShippingAddress()->getShippingAmount(), 2),
            ],
            [
                'label' => 'TAX',
                'amount' => number_format($this->getQuote()->getShippingAddress()->getTaxAmount(), 2),
            ],
        ];
    }

    protected function createPaymentToken(array $applePayPayment)
    {
        $quote = $this->getQuote();

        $paymentMethod = $this->platformApplePayManager->createApplePayToken($quote->getBillingAddress(), $applePayPayment['token']['paymentData']);

        // Set apple pay pay method on quote
        $payment = $quote->getPayment();
        $payment->setMethod('subscribe_pro_applepay');
        $payment->setAdditionalInformation([]);
        $payment->setAdditionalInformation('save_card', false);
        $payment->setAdditionalInformation('is_new_card', true);
        $payment->setAdditionalInformation('payment_token', $paymentMethod->getToken());
        $payment->setAdditionalInformation('is_third_party', false);
        $payment->setAdditionalInformation('subscribe_pro_order_token', '');
        // CC Number
        $ccNumber = $paymentMethod->getFirstSixDigits() . 'XXXXXX' . $paymentMethod->getLastFourDigits();
        $payment->setAdditionalInformation('obscured_cc_number', $ccNumber);
        $payment->setData('cc_number', $ccNumber);
        $payment->setCcNumberEnc($payment->encrypt($ccNumber));
        $payment->setData('cc_exp_month', $paymentMethod->getMonth());
        $payment->setData('cc_exp_year', $paymentMethod->getYear());
        $payment->setData('cc_type', array_search($paymentMethod->getCreditcardType(), $this->spGatewayConfig->getCcTypesMapper()));
        $quote->setPayment($payment);

        // Save quote
        $this->quoteRepository->save($quote);
    }

    protected function createPaymentProfileForCustomer(array $applePayPayment)
    {
        $customer = $this->customerSession->getCustomer();
        $customerEmail = $customer->getEmail();
        $platformCustomer = $this->platformCustomerManager->getCustomer($customerEmail, true);

        $quote = $this->getQuote();
        $billingAddress = $quote->getBillingAddress();
        $paymentProfile = $this->platformApplePayManager->createApplePayProfile($platformCustomer->getId(), $customer, $billingAddress, $applePayPayment);

        $payment = $quote->getPayment();
        $payment->setMethod('subscribe_pro_applepay');
        $payment->setAdditionalInformation([]);
        $payment->setAdditionalInformation('save_card', false);
        $payment->setAdditionalInformation('is_new_card', false);
        $payment->setAdditionalInformation('payment_token', $paymentProfile->getPaymentToken());
        $payment->setAdditionalInformation('payment_profile_id', $paymentProfile->getId());
        $payment->setAdditionalInformation('is_third_party', false);
        $payment->setAdditionalInformation('subscribe_pro_order_token', '');

        $ccNumber = $paymentProfile->getCreditcardFirstDigits() . 'XXXXXX' . $paymentProfile->getCreditcardLastDigits();
        $payment->setAdditionalInformation('obscured_cc_number', $ccNumber);
        $payment->setData('cc_number', $ccNumber);
        $payment->setCcNumberEnc($payment->encrypt($ccNumber));
        $payment->setData('cc_exp_month', $paymentProfile->getCreditcardMonth());
        $payment->setData('cc_exp_year', $paymentProfile->getCreditcardYear());
        $payment->setData('cc_type', array_search($paymentProfile->getCreditcardType(), $this->spGatewayConfig->getCcTypesMapper()));
        $quote->setPayment($payment);

        $this->quoteRepository->save($quote);
    }

    /**
     * Convert the incoming Apple Pay address into a Magento address
     *
     * @param $address
     * @return array
     */
    protected function convertToMagentoAddress($address)
    {
        if (is_string($address)) {
            $address = json_decode($address, true);
        }

        // Retrieve the countryId from the request
        $countries = $this->directoryHelper->getCountryCollection();
        $countryId = strtoupper($address['countryCode']);
        if ((!$countryId || empty($countryId)) && ($countryName = $address['country'])) {
            foreach ($countries as $country) {
                if ($countryName == $country->getName()) {
                    $countryId = strtoupper($country->getCountryId());
                    break;
                }
            }
        }

        $magentoAddress = [
            'street' => implode("\n", $address['addressLines']),
            'firstname' => $address['givenName'],
            'lastname' => $address['familyName'],
            'city' => $address['locality'],
            'country_id' => $countryId,
            'postcode' => $address['postalCode'],
            'telephone' => (isset($address['phoneNumber']) ? $address['phoneNumber'] : '0000000000')
        ];

        // Determine if a region is required for the selected country
        if ($this->directoryHelper->isRegionRequired($countryId) && isset($address['administrativeArea'])) {
            // Lookup region
            $regionModel = $this->regionFactory->create()->loadByCode($address['administrativeArea'], $countryId) ?: $this->regionFactory->create()->loadByName($address['administrativeArea'], $countryId);
            if ($regionModel) {
                $magentoAddress['region_id'] = $regionModel->getId();
                $magentoAddress['region'] = $regionModel->getName();
            }
        }

        return $magentoAddress;
    }

    public function placeOrder()
    {
        $quote = $this->getQuote();
        // QuoteManagement::placeOrder() handles putting through the order the same usual and triggers events and emails
        $this->quoteManagement->placeOrder($quote->getId(), $quote->getPayment());
    }
}
