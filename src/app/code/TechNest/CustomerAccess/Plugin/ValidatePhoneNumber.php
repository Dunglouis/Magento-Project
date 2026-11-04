<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Plugin;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\InputException;
use TechNest\CustomerAccess\Model\PhoneNumberValidator;
use TechNest\CustomerAccess\Setup\Patch\Data\AddPhoneNumberAttribute;

/**
 * Every customer save (storefront, admin, API): a non-empty phone number must be a valid VN mobile number.
 * The value is stored without separators.
 */
class ValidatePhoneNumber
{
    /**
     * @param PhoneNumberValidator $validator
     */
    public function __construct(
        private readonly PhoneNumberValidator $validator
    ) {
    }

    /**
     * Validate and normalize phone_number before the customer is saved
     *
     * @param CustomerRepositoryInterface $subject
     * @param CustomerInterface $customer
     * @param string|null $passwordHash
     * @return array{CustomerInterface, string|null}
     * @throws InputException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSave(
        CustomerRepositoryInterface $subject,
        CustomerInterface $customer,
        $passwordHash = null
    ): array {
        $attribute = $customer->getCustomAttribute(AddPhoneNumberAttribute::ATTRIBUTE_CODE);
        $phone = $attribute ? trim((string)$attribute->getValue()) : '';
        if ($phone !== '') {
            if (!$this->validator->isValid($phone)) {
                throw new InputException(
                    __('Please enter a valid Vietnamese mobile number, for example 0912345678.')
                );
            }
            $customer->setCustomAttribute(
                AddPhoneNumberAttribute::ATTRIBUTE_CODE,
                $this->validator->normalize($phone)
            );
        }
        return [$customer, $passwordHash];
    }
}
