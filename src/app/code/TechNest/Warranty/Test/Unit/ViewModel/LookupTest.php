<?php
declare(strict_types=1);

namespace TechNest\Warranty\Test\Unit\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;
use TechNest\Warranty\Api\WarrantyRepositoryInterface;
use TechNest\Warranty\Model\CoverPeriod;
use TechNest\Warranty\Model\SerialNumber;
use TechNest\Warranty\Model\Source\Status;
use TechNest\Warranty\ViewModel\Lookup;

class LookupTest extends TestCase
{
    public function testMaskNameKeepsFirstLetterOfEachWord(): void
    {
        self::assertSame('N***** V** A*', $this->lookup('')->maskName(' Nguyễn  Văn An '));
        self::assertSame('', $this->lookup('')->maskName(null));
    }

    public function testInvalidSerialGivesFormatError(): void
    {
        self::assertStringContainsString('not a valid serial number', $this->lookup('abc')->getError());
        self::assertSame('', $this->lookup('')->getError());
    }

    /**
     * @param string $serial
     * @return Lookup
     */
    private function lookup(string $serial): Lookup
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn($serial);
        return new Lookup(
            $request,
            $this->createStub(WarrantyRepositoryInterface::class),
            new SerialNumber(),
            new CoverPeriod(),
            new Status(),
            $this->createStub(TimezoneInterface::class),
            $this->createStub(UrlInterface::class)
        );
    }
}
