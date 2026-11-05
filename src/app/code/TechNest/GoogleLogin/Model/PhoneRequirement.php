<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use TechNest\CustomerAccess\Setup\Patch\Data\AddPhoneNumberAttribute;

/**
 * A customer who signed in with Google but has no phone number must add one before using the store
 * (flag in the customer session, checked on every page by Observer\RequirePhoneNumber).
 */
class PhoneRequirement
{
    private const SESSION_KEY = 'technest_google_phone_required';

    /**
     * @param CustomerSession $customerSession
     */
    public function __construct(
        private readonly CustomerSession $customerSession
    ) {
    }

    /**
     * Whether the customer has no phone number yet
     *
     * @param CustomerInterface $customer
     * @return bool
     */
    public function isMissing(CustomerInterface $customer): bool
    {
        $attribute = $customer->getCustomAttribute(AddPhoneNumberAttribute::ATTRIBUTE_CODE);
        return !$attribute || trim((string)$attribute->getValue()) === '';
    }

    /**
     * Ask for the phone number until it is saved
     *
     * @return void
     */
    public function require(): void
    {
        $this->customerSession->setTechnestGooglePhoneRequired(true); // stored as SESSION_KEY
    }

    /**
     * Whether the phone number is still being asked for
     *
     * @return bool
     */
    public function isRequired(): bool
    {
        return (bool)$this->customerSession->getData(self::SESSION_KEY);
    }

    /**
     * Stop asking
     *
     * @return void
     */
    public function clear(): void
    {
        $this->customerSession->unsTechnestGooglePhoneRequired();
    }
}
