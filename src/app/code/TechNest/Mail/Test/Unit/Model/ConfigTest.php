<?php
declare(strict_types=1);

namespace TechNest\Mail\Test\Unit\Model;

use Magento\Config\App\Config\Source\EnvironmentConfigSource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use TechNest\Mail\Model\Config;

class ConfigTest extends TestCase
{
    /**
     * @param mixed $environmentValue EnvironmentConfigSource::get() result ([] when the variable is not set)
     * @param string $adminValue decrypted Admin value
     * @return Config
     */
    private function config(mixed $environmentValue, string $adminValue): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($adminValue);
        $environment = $this->createStub(EnvironmentConfigSource::class);
        $environment->method('get')->willReturn($environmentValue);
        return new Config($scopeConfig, $environment);
    }

    public function testPlainPasswordFromEnvironmentWins(): void
    {
        $this->assertSame('abcdefghijklmnop', $this->config(' abcdefghijklmnop ', 'admin-secret')->getPassword());
    }

    public function testAdminPasswordWithoutEnvironmentVariable(): void
    {
        $this->assertSame('admin-secret', $this->config([], 'admin-secret')->getPassword());
    }
}
