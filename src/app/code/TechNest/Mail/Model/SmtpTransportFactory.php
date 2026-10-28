<?php
declare(strict_types=1);

namespace TechNest\Mail\Model;

use Symfony\Component\Mailer\Transport\Smtp\Auth\LoginAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\Auth\PlainAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Symfony Mailer SMTP transport from the TechNest Mail settings
 */
class SmtpTransportFactory
{
    private const DEFAULT_PORTS = [
        Config::ENCRYPTION_TLS => 587,
        Config::ENCRYPTION_SSL => 465,
        Config::ENCRYPTION_NONE => 25,
    ];

    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * SMTP transport: implicit TLS for SSL, required STARTTLS for TLS, plain text only for "none"
     *
     * @return EsmtpTransport
     */
    public function create(): EsmtpTransport
    {
        $encryption = $this->config->getEncryption();
        $port = $this->config->getPort() ?: (self::DEFAULT_PORTS[$encryption] ?? 587);
        $transport = new EsmtpTransport($this->config->getHost(), $port, $encryption === Config::ENCRYPTION_SSL);

        if ($encryption === Config::ENCRYPTION_TLS) {
            $transport->setRequireTls(true);
        } elseif ($encryption === Config::ENCRYPTION_NONE) {
            $transport->setAutoTls(false);
        }

        $auth = $this->config->getAuth();
        if ($auth === Config::AUTH_NONE || $this->config->getUsername() === '') {
            $transport->setAuthenticators([]);
        } else {
            $transport->setAuthenticators([
                $auth === Config::AUTH_PLAIN ? new PlainAuthenticator() : new LoginAuthenticator()
            ]);
            $transport->setUsername($this->config->getUsername());
            $transport->setPassword($this->config->getPassword());
        }
        return $transport;
    }
}
