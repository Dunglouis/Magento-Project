<?php
declare(strict_types=1);

namespace TechNest\Base\Test\Unit\Model;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Pricing\Price\ConfigurableOptionsProviderInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;
use TechNest\Base\Model\SaleInfo;

class SaleInfoTest extends TestCase
{
    private const VN = 'Asia/Ho_Chi_Minh';

    /**
     * @param string $today
     * @return SaleInfo
     */
    private function saleInfo(string $today = '2026-10-27'): SaleInfo
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('date')->willReturn(new \DateTime($today . ' 10:00:00', new \DateTimeZone(self::VN)));
        $timezone->method('getConfigTimezone')->willReturn(self::VN);
        return new SaleInfo($this->createStub(ConfigurableOptionsProviderInterface::class), $timezone);
    }

    public function testSaleEndsAtMidnightVietnamTime(): void
    {
        // 2026-11-02 23:59:59 at UTC+7 = 16:59:59 UTC
        self::assertSame(
            gmmktime(16, 59, 59, 11, 2, 2026),
            $this->saleInfo()->endOfDay('2026-11-02 00:00:00', self::VN)
        );
    }

    public function testPeriodIncludesBothEndDays(): void
    {
        $saleInfo = $this->saleInfo();

        self::assertTrue($saleInfo->isRunning('2026-10-27 00:00:00', '2026-11-02 00:00:00', '2026-10-27'));
        self::assertTrue($saleInfo->isRunning('2026-10-27', '2026-11-02', '2026-11-02'));
        self::assertTrue($saleInfo->isRunning(null, '2026-11-02', '2026-10-01'));
        self::assertFalse($saleInfo->isRunning('2026-10-28', '2026-11-02', '2026-10-27'));
        self::assertFalse($saleInfo->isRunning('2026-10-20', '2026-10-26', '2026-10-27'));
    }

    /**
     * @param array<string,string|null> $data
     * @return Product
     */
    private function product(array $data): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getData')->willReturnCallback(
            static fn (string $key = '') => $data[$key] ?? null
        );
        $product->method('getTypeId')->willReturn($data['type_id'] ?? 'simple');
        return $product;
    }

    public function testEndsAtIsNullWithoutARunningPeriod(): void
    {
        $saleInfo = $this->saleInfo('2026-10-27');

        self::assertNull($saleInfo->getEndsAt($this->product([])));
        self::assertNull($saleInfo->getEndsAt(
            $this->product(['special_from_date' => '2026-10-20', 'special_to_date' => '2026-10-26'])
        ));
        self::assertSame(
            gmmktime(16, 59, 59, 11, 2, 2026),
            $saleInfo->getEndsAt(
                $this->product(['special_from_date' => '2026-10-27', 'special_to_date' => '2026-11-02'])
            )
        );
    }

    public function testBundlesAndGroupedProductsAreNeverBadged(): void
    {
        self::assertFalse($this->saleInfo()->isOnSale($this->product(['type_id' => 'bundle'])));
        self::assertFalse($this->saleInfo()->isOnSale($this->product(['type_id' => 'grouped'])));
    }
}
