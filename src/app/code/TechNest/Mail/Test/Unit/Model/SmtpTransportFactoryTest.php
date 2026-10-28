<?php
declare(strict_types=1);

namespace TechNest\Mail\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use TechNest\Mail\Model\Config;
use TechNest\Mail\Model\SmtpTransportFactory;

class SmtpTransportFactoryTest extends TestCase
{
    /**
     * @param array<string,string|int> $settings
     * @return SmtpTransportFactory
     */
    private function factory(array $settings): SmtpTransportFactory
    {
        $settings += [
            'host' => 'smtp.gmail.com',
            'port' => 0,
            'encryption' => Config::ENCRYPTION_TLS,
            'auth' => Config::AUTH_LOGIN,
            'username' => 'shop@gmail.com',
            'password' => 'app-password',
        ];
        $config = $this->createStub(Config::class);
        $config->method('getHost')->willReturn($settings['host']);
        $config->method('getPort')->willReturn($settings['port']);
        $config->method('getEncryption')->willReturn($settings['encryption']);
        $config->method('getAuth')->willReturn($settings['auth']);
        $config->method('getUsername')->willReturn($settings['username']);
        $config->method('getPassword')->willReturn($settings['password']);
        return new SmtpTransportFactory($config);
    }

    /**
     * @return array<string,array{0:string,1:int,2:int,3:bool}>
     */
    public static function encryptionProvider(): array
    {
        return [
            'STARTTLS default port' => [Config::ENCRYPTION_TLS, 0, 587, false],
            'SSL default port' => [Config::ENCRYPTION_SSL, 0, 465, true],
            'none, own port' => [Config::ENCRYPTION_NONE, 1025, 1025, false],
        ];
    }

    #[DataProvider('encryptionProvider')]
    public function testPortAndImplicitTls(string $encryption, int $port, int $expectedPort, bool $implicitTls): void
    {
        $transport = $this->factory(['encryption' => $encryption, 'port' => $port])->create();
        /** @var SocketStream $stream */
        $stream = $transport->getStream();

        $this->assertSame('smtp.gmail.com', $stream->getHost());
        $this->assertSame($expectedPort, $stream->getPort());
        $this->assertSame($implicitTls, $stream->isTLS());
    }

    public function testTlsIsRequiredForStarttls(): void
    {
        $transport = $this->factory(['encryption' => Config::ENCRYPTION_TLS])->create();

        $this->assertTrue($transport->isTlsRequired());
        $this->assertTrue($transport->isAutoTls());
    }

    public function testNoEncryptionNeverUpgrades(): void
    {
        $transport = $this->factory(['encryption' => Config::ENCRYPTION_NONE])->create();

        $this->assertFalse($transport->isAutoTls());
        $this->assertFalse($transport->isTlsRequired());
    }

    public function testCredentialsAreSetForLogin(): void
    {
        $transport = $this->factory([])->create();

        $this->assertSame('shop@gmail.com', $transport->getUsername());
        $this->assertSame('app-password', $transport->getPassword());
    }

    public function testNoCredentialsWithoutAuth(): void
    {
        $transport = $this->factory(['auth' => Config::AUTH_NONE])->create();

        $this->assertSame('', $transport->getUsername());
        $this->assertSame('', $transport->getPassword());
    }
}
