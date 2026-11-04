<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Model;

use Magento\Config\App\Config\Source\EnvironmentConfigSource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;

/**
 * Google OAuth client settings (Stores > Configuration > TechNest > Google Login,
 * or CONFIG__DEFAULT__TECHNEST_GOOGLE__GENERAL__* env vars)
 */
class Config
{
    public const CALLBACK_ROUTE = 'technest_google/oauth/callback';

    private const PATH = 'technest_google/general/';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EnvironmentConfigSource $environment
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EnvironmentConfigSource $environment,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Whether the "Sign in with Google" button is offered (needs client ID and secret)
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enabled')
            && $this->getClientId() !== ''
            && $this->getClientSecret() !== '';
    }

    /**
     * OAuth client ID
     *
     * @return string
     */
    public function getClientId(): string
    {
        return $this->value('client_id');
    }

    /**
     * Client secret: plain from the CLIENT_SECRET env variable, else the encrypted Admin value
     *
     * The environment value is read raw because scope config runs every value of this path through
     * the decrypting backend model, which turns a plain secret into ''.
     *
     * @return string
     */
    public function getClientSecret(): string
    {
        $plain = $this->environment->get('default/' . self::PATH . 'client_secret');
        return is_string($plain) && trim($plain) !== '' ? trim($plain) : $this->value('client_secret');
    }

    /**
     * Redirect URI registered on the OAuth client; defaults to this store's callback URL
     *
     * @return string
     */
    public function getRedirectUri(): string
    {
        return $this->value('redirect_uri')
            ?: $this->urlBuilder->getUrl(self::CALLBACK_ROUTE, ['_nosid' => true, '_secure' => true]);
    }

    /**
     * Trimmed setting value
     *
     * @param string $field
     * @return string
     */
    private function value(string $field): string
    {
        return trim((string)$this->scopeConfig->getValue(self::PATH . $field));
    }
}
