<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Plugin;

use Magento\Customer\Block\Form\Login\Info as LoginInfo;
use TechNest\CustomerAccess\Model\CheckoutLoginIntent;

/**
 * On the sign-in page reached from checkout, "Create an Account" keeps the way back to checkout.
 */
class LoginPageCreateAccountUrl
{
    /**
     * @param CheckoutLoginIntent $checkoutLoginIntent
     */
    public function __construct(
        private readonly CheckoutLoginIntent $checkoutLoginIntent
    ) {
    }

    /**
     * Append ?tn_checkout=1 while the intent is active
     *
     * @param LoginInfo $subject
     * @param string $result
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetCreateAccountUrl(LoginInfo $subject, $result): string
    {
        if (!$this->checkoutLoginIntent->isActive()) {
            return (string)$result;
        }
        $separator = str_contains((string)$result, '?') ? '&' : '?';
        return $result . $separator . CheckoutLoginIntent::PARAM . '=1';
    }
}
