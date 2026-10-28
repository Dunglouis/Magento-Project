<?php
declare(strict_types=1);

namespace TechNest\Mail\Plugin;

use Magento\Email\Model\Transport;
use Magento\Framework\Mail\EmailMessage;
use Symfony\Component\Mailer\Transport\TransportInterface as SymfonyTransportInterface;
use Symfony\Component\Mime\Address;
use TechNest\Mail\Model\Config;
use TechNest\Mail\Model\SmtpTransportFactory;

/**
 * When TechNest Mail is enabled, core's mail transport sends through our SMTP server instead of
 * sendmail / system/smtp, and every sender address is replaced by the configured one (names stay).
 */
class SendThroughSmtp
{
    /**
     * @param Config $config
     * @param SmtpTransportFactory $transportFactory
     */
    public function __construct(
        private readonly Config $config,
        private readonly SmtpTransportFactory $transportFactory
    ) {
    }

    /**
     * Use the TechNest SMTP server
     *
     * @param Transport $subject
     * @param callable $proceed
     * @return SymfonyTransportInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGetTransport(Transport $subject, callable $proceed): SymfonyTransportInterface
    {
        return $this->config->isEnabled() ? $this->transportFactory->create() : $proceed();
    }

    /**
     * Send from the configured address, keeping the sender name (e.g. "TechNest")
     *
     * @param Transport $subject
     * @return null
     */
    public function beforeSendMessage(Transport $subject)
    {
        $fromEmail = $this->config->getFromEmail();
        $message = $subject->getMessage();
        if (!$this->config->isEnabled() || $fromEmail === '' || !$message instanceof EmailMessage) {
            return null;
        }
        $headers = $message->getSymfonyMessage()->getHeaders();
        /** @var \Symfony\Component\Mime\Header\MailboxListHeader|null $from */
        $from = $headers->get('From');
        $name = $from && $from->getAddresses() ? $from->getAddresses()[0]->getName() : '';
        $headers->remove('From');
        $headers->addMailboxListHeader('From', [new Address($fromEmail, $name)]);
        return null;
    }
}
