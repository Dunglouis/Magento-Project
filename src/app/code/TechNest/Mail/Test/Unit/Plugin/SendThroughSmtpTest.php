<?php
declare(strict_types=1);

namespace TechNest\Mail\Test\Unit\Plugin;

use Magento\Email\Model\Transport;
use Magento\Framework\Mail\EmailMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use TechNest\Mail\Model\Config;
use TechNest\Mail\Model\SmtpTransportFactory;
use TechNest\Mail\Plugin\SendThroughSmtp;

class SendThroughSmtpTest extends TestCase
{
    /**
     * Run beforeSendMessage on a message sent by "TechNest <sales@example.com>"
     *
     * @param bool $enabled
     * @param string $fromEmail
     * @return Address[]
     */
    private function sendFrom(bool $enabled, string $fromEmail): array
    {
        $email = (new Email())->from(new Address('sales@example.com', 'TechNest'))->to('qa@technest.test');
        $message = $this->createStub(EmailMessage::class);
        $message->method('getSymfonyMessage')->willReturn($email);
        $transport = $this->createStub(Transport::class);
        $transport->method('getMessage')->willReturn($message);
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('getFromEmail')->willReturn($fromEmail);

        (new SendThroughSmtp($config, $this->createStub(SmtpTransportFactory::class)))->beforeSendMessage($transport);
        return $email->getFrom();
    }

    public function testSenderAddressIsReplacedAndNameKept(): void
    {
        $from = $this->sendFrom(true, 'shop@gmail.com');

        $this->assertCount(1, $from);
        $this->assertSame('shop@gmail.com', $from[0]->getAddress());
        $this->assertSame('TechNest', $from[0]->getName());
    }

    public function testSenderIsKeptWithoutFromEmail(): void
    {
        $this->assertSame('sales@example.com', $this->sendFrom(true, '')[0]->getAddress());
    }

    public function testSenderIsKeptWhenDisabled(): void
    {
        $this->assertSame('sales@example.com', $this->sendFrom(false, 'shop@gmail.com')[0]->getAddress());
    }
}
