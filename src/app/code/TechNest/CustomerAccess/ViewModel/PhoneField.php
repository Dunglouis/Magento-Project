<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\ViewModel;

use Magento\Customer\Block\Form\Edit as EditForm;
use Magento\Customer\Block\Form\Register as RegisterForm;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
use TechNest\CustomerAccess\Model\PhoneNumberValidator;
use TechNest\CustomerAccess\Setup\Patch\Data\AddPhoneNumberAttribute;

/**
 * Phone number input on the register and account edit forms.
 */
class PhoneField implements ArgumentInterface
{
    /**
     * Input name / attribute code
     *
     * @return string
     */
    public function getName(): string
    {
        return AddPhoneNumberAttribute::ATTRIBUTE_CODE;
    }

    /**
     * Client-side validation rules (mage/validation)
     *
     * @return array<string,mixed>
     */
    public function getValidationRules(): array
    {
        return ['required' => true, 'pattern' => PhoneNumberValidator::PATTERN];
    }

    /**
     * Current value: posted data after a failed registration, or the saved value on the edit form
     *
     * @param LayoutInterface $layout
     * @return string
     */
    public function getValue(LayoutInterface $layout): string
    {
        $register = $layout->getBlock('customer_form_register');
        if ($register instanceof RegisterForm) {
            return (string)$register->getFormData()->getData($this->getName());
        }

        $edit = $layout->getBlock('customer_edit');
        if ($edit instanceof EditForm) {
            $attribute = $edit->getCustomer()->getCustomAttribute($this->getName());
            return $attribute ? (string)$attribute->getValue() : '';
        }
        return '';
    }
}
