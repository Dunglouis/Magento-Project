<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Controller\Oauth;

use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use TechNest\GoogleLogin\Model\AccountLinker;
use TechNest\GoogleLogin\Model\OAuthClient;
use TechNest\GoogleLogin\Model\PhoneRequirement;

/**
 * technest_google/oauth/callback: check state, exchange the code (with the PKCE verifier), sign the customer in.
 * Customers without a phone number go to the phone page first; then on to checkout or the account,
 * as after a normal sign-in.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Callback implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param OAuthClient $oauthClient
     * @param AccountLinker $accountLinker
     * @param PhoneRequirement $phoneRequirement
     * @param StoreManagerInterface $storeManager
     * @param AccountRedirect $accountRedirect
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly OAuthClient $oauthClient,
        private readonly AccountLinker $accountLinker,
        private readonly PhoneRequirement $phoneRequirement,
        private readonly StoreManagerInterface $storeManager,
        private readonly AccountRedirect $accountRedirect,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $pending = $this->customerSession->getData(Start::SESSION_KEY);
        $this->customerSession->unsTechnestGoogleOauth(); // one use only

        if ($this->customerSession->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account');
        }
        if ($this->request->getParam('error')) {
            $this->messageManager->addNoticeMessage(__('Google sign-in was cancelled.'));
            return $this->toLogin();
        }

        $state = (string)$this->request->getParam('state');
        $code = (string)$this->request->getParam('code');
        if (!is_array($pending) || $code === '' || $state === ''
            || !hash_equals((string)($pending['state'] ?? ''), $state)
            || (int)($pending['expires'] ?? 0) < time()
        ) {
            $this->messageManager->addErrorMessage(__('Your Google sign-in has expired. Please try again.'));
            return $this->toLogin();
        }

        try {
            $profile = $this->oauthClient->fetchProfile($code, (string)$pending['verifier']);
            $store = $this->storeManager->getStore();
            $customer = $this->accountLinker->resolve($profile, (int)$store->getWebsiteId(), (int)$store->getId());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->toLogin();
        } catch (\Throwable $e) {
            $this->logger->error('TechNest_GoogleLogin: sign-in failed', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Google sign-in failed. Please try again.'));
            return $this->toLogin();
        }

        $this->customerSession->setCustomerDataAsLoggedIn($customer);
        $this->customerSession->regenerateId();
        $this->resetPrivateContentCookie();

        if ($this->phoneRequirement->isMissing($customer)) {
            $this->phoneRequirement->require();
            return $this->redirectFactory->create()->setPath('technest_google/phone');
        }
        return $this->accountRedirect->getRedirect();
    }

    /**
     * Back to the sign-in page
     *
     * @return Redirect
     */
    private function toLogin(): Redirect
    {
        return $this->redirectFactory->create()->setPath('customer/account/login');
    }

    /**
     * Make the storefront reload the customer's private content (cart, name), as core sign-in does
     *
     * @return void
     */
    private function resetPrivateContentCookie(): void
    {
        if ($this->cookieManager->getCookie('mage-cache-sessid')) {
            $metadata = $this->cookieMetadataFactory->createCookieMetadata()->setPath('/');
            $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
        }
    }
}
