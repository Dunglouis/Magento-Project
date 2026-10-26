<?php
declare(strict_types=1);

namespace TechNest\Base\Test\Unit\Plugin;

use Magento\Directory\Model\Currency;
use Magento\Framework\Locale\Format;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TechNest\Base\Plugin\ZeroDecimalCurrency;

class ZeroDecimalCurrencyTest extends TestCase
{
    private function plugin(string $currentCurrency = 'USD'): ZeroDecimalCurrency
    {
        $store = $this->createStub(Store::class);
        $store->method('getCurrentCurrencyCode')->willReturn($currentCurrency);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        return new ZeroDecimalCurrency($storeManager);
    }

    private function currency(string $code): Currency
    {
        $currency = $this->createStub(Currency::class);
        $currency->method('getCode')->willReturn($code);
        return $currency;
    }

    public function testVndIsFormattedWithoutDecimals(): void
    {
        [$price, $options] = $this->plugin()->beforeFormatTxt($this->currency('VND'), 21475000.0, ['display' => 1]);

        self::assertSame(21475000.0, $price);
        self::assertSame(['display' => 1, 'precision' => 0], $options);
    }

    public function testUsdKeepsItsOptions(): void
    {
        [, $options] = $this->plugin()->beforeFormatTxt($this->currency('USD'), 859.0, ['precision' => 2]);

        self::assertSame(['precision' => 2], $options);
    }

    public function testJsPriceFormatUsesExplicitOrCurrentCurrency(): void
    {
        $format = $this->createStub(Format::class);
        $base = ['pattern' => '%s ₫', 'precision' => 2, 'requiredPrecision' => 2];

        $explicit = $this->plugin('USD')->afterGetPriceFormat($format, $base, null, 'VND');
        $current = $this->plugin('VND')->afterGetPriceFormat($format, $base);
        $usd = $this->plugin('USD')->afterGetPriceFormat($format, $base);

        self::assertSame(0, $explicit['precision']);
        self::assertSame(0, $current['requiredPrecision']);
        self::assertSame(2, $usd['precision']);
    }
}
