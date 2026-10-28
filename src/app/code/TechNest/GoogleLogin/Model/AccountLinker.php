<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use TechNest\GoogleLogin\Model\ResourceModel\GoogleAccount;

/**
 * Google account -> customer: the linked customer, else the customer with the same email (linked now),
 * else a new customer without password (Magento sends the "set your password" welcome email).
 */
class AccountLinker
{
    /**
     * @param GoogleAccount $googleAccount
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerInterfaceFactory $customerFactory
     * @param AccountManagementInterface $accountManagement
     */
    public function __construct(
        private readonly GoogleAccount $googleAccount,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly AccountManagementInterface $accountManagement
    ) {
    }

    /**
     * Customer to sign in as
     *
     * @param GoogleProfile $profile
     * @param int $websiteId
     * @param int $storeId store the new customer registers in (its emails use this store's language)
     * @return CustomerInterface
     * @throws LocalizedException
     */
    public function resolve(GoogleProfile $profile, int $websiteId, int $storeId): CustomerInterface
    {
        $linkedId = $this->googleAccount->getCustomerIdBySub($profile->sub);
        if ($linkedId !== null) {
            return $this->customerRepository->getById($linkedId);
        }

        try {
            $customer = $this->customerRepository->get($profile->email, $websiteId);
        } catch (NoSuchEntityException) {
            $customer = $this->accountManagement->createAccount(
                $this->customerFactory->create()
                    ->setEmail($profile->email)
                    ->setFirstname($profile->firstname)
                    ->setLastname($profile->lastname)
                    ->setWebsiteId($websiteId)
                    ->setStoreId($storeId)
            );
        }

        if ($this->googleAccount->getSubByCustomerId((int)$customer->getId()) !== null) {
            throw new LocalizedException(
                __('The account %1 is already linked to another Google account.', $profile->email)
            );
        }
        $this->googleAccount->link($profile->sub, (int)$customer->getId(), $profile->email);
        return $customer;
    }
}
