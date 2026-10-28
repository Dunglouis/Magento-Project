<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Controller\Oauth;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use TechNest\GoogleLogin\Model\Config;
use TechNest\GoogleLogin\Model\OAuthClient;
use TechNest\GoogleLogin\Model\Pkce;

/**
 * technest_google/oauth/start: keep state + PKCE verifier in the session and send the shopper to Google
 */
class Start implements HttpGetActionInterface
{
    public const SESSION_KEY = 'technest_google_oauth';
    public const LIFETIME = 600;

    /**
     * @param CustomerSession $customerSession
     * @param Config $config
     * @param Pkce $pkce
     * @param OAuthClient $oauthClient
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly Config $config,
        private readonly Pkce $pkce,
        private readonly OAuthClient $oauthClient,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();
        if ($this->customerSession->isLoggedIn()) {
            return $redirect->setPath('customer/account');
        }
        if (!$this->config->isEnabled()) {
            $this->messageManager->addErrorMessage(__('Sign in with Google is not available right now.'));
            return $redirect->setPath('customer/account/login');
        }

        $state = $this->pkce->createState();
        $verifier = $this->pkce->createVerifier();
        $this->customerSession->setTechnestGoogleOauth([ // stored as SESSION_KEY
            'state' => $state,
            'verifier' => $verifier,
            'expires' => time() + self::LIFETIME,
        ]);
        return $redirect->setUrl($this->oauthClient->getAuthorizationUrl($state, $this->pkce->challenge($verifier)));
    }
}
