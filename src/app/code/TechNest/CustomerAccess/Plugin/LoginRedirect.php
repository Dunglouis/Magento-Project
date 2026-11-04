<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Plugin;

use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\Controller\Result\Redirect as RedirectResult;
use Magento\Framework\UrlInterface;
use TechNest\CustomerAccess\Model\CheckoutLoginIntent;

/**
 * After sign-in/registration: back to checkout when the guest came from checkout,
 * otherwise the home page instead of the account dashboard. Other return URLs Magento set
 * (e.g. "add to wish list" while signed out) are left alone.
 */
class LoginRedirect
{
    /**
     * @var string|null Return URL Magento would use, captured before it is consumed
     */
    private ?string $target = null;

    /**
     * @param CustomerSession $customerSession
     * @param CustomerUrl $customerUrl
     * @param UrlInterface $urlBuilder
     * @param CheckoutLoginIntent $checkoutLoginIntent
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CustomerUrl $customerUrl,
        private readonly UrlInterface $urlBuilder,
        private readonly CheckoutLoginIntent $checkoutLoginIntent
    ) {
    }

    /**
     * Remember the pending return URL
     *
     * @param AccountRedirect $subject
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeGetRedirect(AccountRedirect $subject): void
    {
        $this->target = (string)$this->customerSession->getBeforeAuthUrl();
    }

    /**
     * Send default sign-ins home
     *
     * @param AccountRedirect $subject
     * @param mixed $result
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetRedirect(AccountRedirect $subject, $result)
    {
        if ($this->checkoutLoginIntent->isActive()) {
            $this->checkoutLoginIntent->clear();
            return $result;
        }
        if ($result instanceof RedirectResult && $this->isDefaultTarget((string)$this->target)) {
            $result->setUrl($this->urlBuilder->getBaseUrl());
        }
        return $result;
    }

    /**
     * Whether Magento would fall back to the account dashboard (or back to the sign-in page itself)
     *
     * @param string $url
     * @return bool
     */
    private function isDefaultTarget(string $url): bool
    {
        $defaults = array_map([$this, 'normalize'], [
            $this->urlBuilder->getBaseUrl(),
            $this->customerUrl->getAccountUrl(),
            $this->customerUrl->getDashboardUrl(),
            $this->customerUrl->getLoginUrl(),
            $this->customerUrl->getLogoutUrl(),
        ]);
        return $url === '' || in_array($this->normalize($url), $defaults, true);
    }

    /**
     * Compare URLs without query string (e.g. "?___store=vi") and trailing slash
     *
     * @param string $url
     * @return string
     */
    private function normalize(string $url): string
    {
        return rtrim((string)strtok($url, '?'), '/');
    }
}
