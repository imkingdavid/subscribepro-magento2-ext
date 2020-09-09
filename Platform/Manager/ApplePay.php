<?php

namespace Swarming\SubscribePro\Platform\Manager;

use SubscribePro\Service\Address\AddressInterface;
use SubscribePro\Service\PaymentProfile\PaymentProfileInterface;
use Swarming\SubscribePro\Platform\Service\PaymentProfile;
use Swarming\SubscribePro\Platform\Service\Token;

class ApplePay
{
    /**
     * @var PaymentProfile
     */
    protected $platformPaymentProfileService;

    /**
     * @var Token
     */
    protected $platformTokenService;

    /**
     * @param PaymentProfile $platformPaymentProfileService
     * @param Token $platformTokenService
     */
    public function __construct(
        PaymentProfile $platformPaymentProfileService,
        Token $platformTokenService
    ) {
        $this->platformPaymentProfileService = $platformPaymentProfileService;
        $this->platformTokenService = $platformTokenService;
    }

    /**
     * @param $spCustomerId
     * @param \Magento\Customer\Model\Customer|null $customer
     * @param \Magento\Quote\Model\Quote\Address|null $billingAddress
     * @param array $applePayPaymentData
     * @return PaymentProfileInterface
     */
    public function createApplePayProfile($spCustomerId, \Magento\Customer\Model\Customer $customer = null, \Magento\Quote\Model\Quote\Address $billingAddress = null, array $applePayPaymentData = [])
    {
        $paymentProfile = $this->platformPaymentProfileService->createApplePayProfile();

        if (null !== $customer) {
            $paymentProfile = $this->initProfileWithCustomerDefault($paymentProfile, $customer);
        }

        if (null !== $billingAddress) {
            $spBillingAddress = $this->mapMagentoAddressToPlatform($billingAddress, $paymentProfile->getBillingAddress());
            $paymentProfile->setBillingAddress($spBillingAddress);
        }

        $paymentProfile->setCustomerId($spCustomerId);
        $paymentProfile->setApplePayPaymentData($applePayPaymentData);

        $this->platformPaymentProfileService->saveProfile($paymentProfile);

        return $paymentProfile;
    }

    public function createApplePayToken(\Magento\Quote\Model\Quote\Address $billingAddress = null, array $applePayPaymentData = [])
    {
        $requestData = [
            'billing_address' => [
                'first_name' => $billingAddress->getData('firstname'),
                'last_name' => $billingAddress->getData('lastname'),
            ],
            'applepay_payment_data' => $applePayPaymentData,
        ];

        $optionalFields = ['company' => 'company', 'city' => 'city', 'postcode' => 'postcode', 'country' => 'country_id', 'phone' => 'telephone'];
        foreach ($optionalFields as $fieldKey => $magentoFieldKey) {
            if (strlen($billingAddress->getData($magentoFieldKey))) {
                $requestData['billing_address'][$fieldKey] = $billingAddress->getData($magentoFieldKey);
            }
        }
        if (strlen($billingAddress->getStreet1())) {
            $requestData['billing_address']['street1'] = $billingAddress->getStreet1();
        }
        if (strlen($billingAddress->getStreet2())) {
            $requestData['billing_address']['street2'] = $billingAddress->getStreet2();
        }
        if (strlen($billingAddress->getRegionCode())) {
            $requestData['billing_address']['region'] = $billingAddress->getRegionCode();
        }

        // Create token
        $token = $this->platformTokenService->createToken($requestData);
        $token = $this->platformTokenService->saveToken($token);

        return $token;
    }

    /**
     * Init profile with customer data from customer record
     *
     * @param PaymentProfileInterface $paymentProfile
     * @param $customer
     * @return PaymentProfileInterface
     */
    public function initProfileWithCustomerDefault(PaymentProfileInterface $paymentProfile, \Magento\Customer\Model\Customer $customer)
    {
        // Grab billing address
        $billingAddress = $customer->getDefaultBillingAddress();
        // Add address data if default billing addy exists
        if ($billingAddress) {
            // Map
            $this->mapMagentoAddressToPlatform($billingAddress, $paymentProfile->getBillingAddress());
        } else {
            // Empty(ish) billing address
            $paymentProfile->getBillingAddress()->setFirstName($customer->getData('firstname'));
            $paymentProfile->getBillingAddress()->setLastName($customer->getData('lastname'));
        }

        return $paymentProfile;
    }

    /**
     * @param \Magento\Customer\Model\Address $magentoAddress
     * @param AddressInterface $platformAddress
     * @return AddressInterface
     */
    protected function mapMagentoAddressToPlatform(\Magento\Customer\Model\Address $magentoAddress, AddressInterface $platformAddress)
    {
        $platformAddress->setFirstName($magentoAddress->getData('firstname'));
        $platformAddress->setLastName($magentoAddress->getData('lastname'));
        $platformAddress->setCompany($magentoAddress->getData('company'));
        $platformAddress->setStreet1((string) $magentoAddress->getStreetLine(1));
        if (strlen($magentoAddress->getStreetLine(2))) {
            $platformAddress->setStreet2((string) $magentoAddress->getStreetLine(2));
        } else {
            $platformAddress->setStreet2(null);
        }
        $platformAddress->setCity($magentoAddress->getData('city'));
        $platformAddress->setRegion($magentoAddress->getRegionCode());
        $platformAddress->setPostcode($magentoAddress->getData('postcode'));
        $platformAddress->setCountry($magentoAddress->getData('country_id'));
        $platformAddress->setPhone($magentoAddress->getData('telephone'));

        return $platformAddress;
    }
}
