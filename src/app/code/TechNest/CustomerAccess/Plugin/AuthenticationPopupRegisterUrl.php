<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Plugin;

use Magento\Customer\Block\Account\AuthenticationPopup;
use Magento\Framework\UrlInterface;
use TechNest\CustomerAccess\Model\CheckoutLoginIntent;

/**
 * The checkout sign-in popup's "Create an Account" link marks the registration as "on the way to checkout".
 */
class AuthenticationPopupRegisterUrl
{
    /**
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Add ?tn_checkout=1 to the register URL
     *
     * @param AuthenticationPopup $subject
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetConfig(AuthenticationPopup $subject, array $result): array
    {
        $result['customerRegisterUrl'] = $this->urlBuilder->getUrl(
            'customer/account/create',
            ['_query' => [CheckoutLoginIntent::PARAM => 1]]
        );
        return $result;
    }
}
