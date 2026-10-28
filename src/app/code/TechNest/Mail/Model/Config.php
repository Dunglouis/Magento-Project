<?php
declare(strict_types=1);

namespace TechNest\Mail\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * SMTP settings (Stores > Configuration > TechNest > Mail, or CONFIG__DEFAULT__TECHNEST_MAIL__SMTP__* env vars)
 */
class Config
{
    public const ENCRYPTION_TLS = 'tls';
    public const ENCRYPTION_SSL = 'ssl';
    public const ENCRYPTION_NONE = 'none';
    public const AUTH_LOGIN = 'login';
    public const AUTH_PLAIN = 'plain';
    public const AUTH_NONE = 'none';

    private const PATH = 'technest_mail/smtp/';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether store emails go through the SMTP server below (a host is required)
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enabled') && $this->getHost() !== '';
    }

    /**
     * SMTP server host name
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->value('host');
    }

    /**
     * SMTP port, 0 for the default of the encryption
     *
     * @return int
     */
    public function getPort(): int
    {
        return (int)$this->value('port');
    }

    /**
     * One of the ENCRYPTION_* constants
     *
     * @return string
     */
    public function getEncryption(): string
    {
        return $this->value('encryption') ?: self::ENCRYPTION_TLS;
    }

    /**
     * One of the AUTH_* constants
     *
     * @return string
     */
    public function getAuth(): string
    {
        return $this->value('auth') ?: self::AUTH_LOGIN;
    }

    /**
     * SMTP user name
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->value('username');
    }

    /**
     * Decrypted password (stored encrypted in core_config_data, or plain in the environment)
     *
     * @return string
     */
    public function getPassword(): string
    {
        return $this->value('password');
    }

    /**
     * Address that replaces every store sender address, or '' to keep them
     *
     * @return string
     */
    public function getFromEmail(): string
    {
        return $this->value('from_email');
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
