<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Test\Unit\Model\Step;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\Step\Accounts;

class AccountsTest extends TestCase
{
    /**
     * @var Accounts
     */
    private Accounts $step;

    protected function setUp(): void
    {
        $this->step = new Accounts(
            $this->createStub(SeedData::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(CustomerInterfaceFactory::class),
            $this->createStub(AddressInterfaceFactory::class),
            $this->createStub(RegionInterfaceFactory::class),
            $this->createStub(EncryptorInterface::class)
        );
    }

    public function testMissingOrShortPasswordSkipsTheAccount(): void
    {
        self::assertNull($this->step->password(false));
        self::assertNull($this->step->password(''));
        self::assertNull($this->step->password('  short  '));
    }

    public function testPasswordIsTrimmed(): void
    {
        self::assertSame('Qa-12345678', $this->step->password(" Qa-12345678\n"));
    }
}
