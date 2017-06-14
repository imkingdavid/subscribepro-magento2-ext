<?php

namespace Swarming\SubscribePro\Platform\Manager;

use SubscribePro\Service\Customer\CustomerInterface as PlatformCustomerInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;


class Customer
{
    /**
     * @var \Swarming\SubscribePro\Platform\Service\Customer
     */
    protected $platformCustomerService;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @param \Swarming\SubscribePro\Platform\Service\Customer $platformCustomerService
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        \Swarming\SubscribePro\Platform\Service\Customer $platformCustomerService,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
    ) {
        $this->platformCustomerService = $platformCustomerService;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param int $customerId
     * @param bool $createIfNotExist
     * @param int|null $websiteId
     * @return \SubscribePro\Service\Customer\CustomerInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \SubscribePro\Exception\HttpException
     */
    public function getCustomerById($customerId, $createIfNotExist = false, $websiteId = null)
    {
        $customer = $this->customerRepository->getById($customerId);
        return $this->getCustomer($customer->getEmail(), $createIfNotExist, $websiteId);
    }

    /**
     * @param string $customerEmail
     * @param bool $createIfNotExist
     * @param int|null $websiteId
     * @return \SubscribePro\Service\Customer\CustomerInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \SubscribePro\Exception\HttpException
     */
    public function getCustomer($customerEmail, $createIfNotExist = false, $websiteId = null)
    {
        $platformCustomers = $this->platformCustomerService->loadCustomers(
            [PlatformCustomerInterface::EMAIL => $customerEmail],
            $websiteId
        );

        if (!empty($platformCustomers)) {
            $platformCustomer = $platformCustomers[0];
        } else if ($createIfNotExist) {
            $customer = $this->customerRepository->get($customerEmail, $websiteId);
            $platformCustomer = $this->createPlatformCustomer($customer, $websiteId);
        } else {
            file_put_contents("/var/www/john-m2-1-6.spr0.com/var/log/test.log", SubGenerateCallTrace(), FILE_APPEND | LOCK_EX);
            throw new NoSuchEntityException(__('Platform customer is not found.'));
        }

        return $platformCustomer;
    }

    /**
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param int|null $websiteId
     * @return \SubscribePro\Service\Customer\CustomerInterface
     * @throws \SubscribePro\Exception\HttpException
     */
    protected function createPlatformCustomer(CustomerInterface $customer, $websiteId = null)
    {
        $platformCustomer = $this->platformCustomerService->createCustomer([], $websiteId);
        $platformCustomer->setMagentoCustomerId($customer->getId());
        $platformCustomer->setEmail($customer->getEmail());
        $platformCustomer->setFirstName($customer->getFirstname());
        $platformCustomer->setMiddleName($customer->getMiddlename());
        $platformCustomer->setLastName($customer->getLastname());
        $platformCustomer->setMagentoCustomerGroupId($customer->getGroupId());
        $platformCustomer->setMagentoWebsiteId($customer->getWebsiteId());

        $this->platformCustomerService->saveCustomer($platformCustomer, $websiteId);
        return $platformCustomer;
    }
}

function SubGenerateCallTrace()
{
    $e = new Exception();
    $trace = explode("\n", $e->getTraceAsString());
    // reverse array to make steps line up chronologically
    $trace = array_reverse($trace);
    array_shift($trace); // remove {main}
    array_pop($trace); // remove call to this method
    $length = count($trace);
    $result = array();

    for ($i = 0; $i < $length; $i++)
    {
        $result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
    }

    return "\t" . implode("\n\t", $result);
}
