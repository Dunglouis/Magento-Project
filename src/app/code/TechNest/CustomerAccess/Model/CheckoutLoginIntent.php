<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;

/**
 * Remembers that a guest was sent to sign in / register on the way to checkout,
 * so only that sign-in returns to checkout (any other sign-in goes to the home page).
 */
class CheckoutLoginIntent
{
    private const SESSION_KEY = 'technest_checkout_login';

    /** Query parameter that carries the intent to the sign-in / registration page */
    public const PARAM = 'tn_checkout';

    /** Sign-in / registration pages: the intent depends on PARAM (they are never served from page cache) */
    public const ENTRY_ACTIONS = ['customer_account_login', 'customer_account_create'];

    /** Requests that keep the intent alive while the guest signs in or registers */
    public const KEEP_ON_ACTIONS = [
        'customer_account_loginpost',
        'customer_account_createpost',
        'customer_account_forgotpassword',
        'customer_account_forgotpasswordpost',
        'customer_section_load',
        'checkout_index_index',
        // TechNest_GoogleLogin: Google round trip and the phone number page after it
        'technest_google_oauth_start',
        'technest_google_oauth_callback',
        'technest_google_phone_index',
        'technest_google_phone_save',
    ];

    /**
     * @param CustomerSession $customerSession
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Start: sign-in/registration should end at checkout
     *
     * @return void
     */
    public function start(): void
    {
        $this->customerSession->setTechnestCheckoutLogin(true); // stored as SESSION_KEY
        $this->customerSession->setBeforeAuthUrl($this->getCheckoutUrl());
    }

    /**
     * Whether the guest is signing in on the way to checkout
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool)$this->customerSession->getData(self::SESSION_KEY);
    }

    /**
     * Forget the intent and any checkout return URL, including one left without the flag by older sessions
     *
     * @return void
     */
    public function clear(): void
    {
        if ($this->isActive()) {
            $this->customerSession->unsTechnestCheckoutLogin();
        }
        $beforeAuthUrl = (string)$this->customerSession->getBeforeAuthUrl();
        if ($beforeAuthUrl !== '' && $this->normalize($beforeAuthUrl) === $this->normalize($this->getCheckoutUrl())) {
            $this->customerSession->unsBeforeAuthUrl();
        }
    }

    /**
     * Whether the URL is the cart page (also "checkout/cart/index", with or without query string)
     *
     * @param string $url
     * @return bool
     */
    public function isCartUrl(string $url): bool
    {
        return $url !== '' && $this->normalize($url) === $this->normalize($this->urlBuilder->getUrl('checkout/cart'));
    }

    /**
     * Checkout page URL
     *
     * @return string
     */
    public function getCheckoutUrl(): string
    {
        return $this->urlBuilder->getUrl('checkout');
    }

    /**
     * Compare URLs without query string (e.g. "?___store=vi"), trailing slash and default "index" action
     *
     * @param string $url
     * @return string
     */
    private function normalize(string $url): string
    {
        $url = rtrim((string)strtok($url, '?#'), '/');
        while (str_ends_with($url, '/index')) {
            $url = substr($url, 0, -strlen('/index'));
        }
        return $url;
    }
}
