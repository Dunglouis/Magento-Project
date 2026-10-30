<?php
declare(strict_types=1);

namespace TechNest\Shipping\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use TechNest\Shipping\Model\FeeCalculator;
use TechNest\Shipping\Model\Provinces;

class FeeCalculatorTest extends TestCase
{
    private const FEES = [
        Provinces::NORTH => 2.0,
        Provinces::CENTRAL => 4.0,
        Provinces::SOUTH => 5.0,
        'default' => 6.0,
    ];

    /**
     * @var FeeCalculator
     */
    private FeeCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new FeeCalculator();
    }

    public function testFeeByZone(): void
    {
        $this->assertSame(2.0, $this->calculator->calculate(Provinces::NORTH, self::FEES, false, 100, 500));
        $this->assertSame(4.0, $this->calculator->calculate(Provinces::CENTRAL, self::FEES, false, 100, 500));
        $this->assertSame(5.0, $this->calculator->calculate(Provinces::SOUTH, self::FEES, false, 100, 500));
    }

    public function testUnknownProvinceUsesDefaultFee(): void
    {
        $this->assertSame(6.0, $this->calculator->calculate(null, self::FEES, false, 100, 500));
    }

    public function testFreeShippingCoupon(): void
    {
        $this->assertSame(0.0, $this->calculator->calculate(Provinces::SOUTH, self::FEES, true, 10, 500));
    }

    public function testFreeFromThreshold(): void
    {
        $this->assertSame(0.0, $this->calculator->calculate(Provinces::SOUTH, self::FEES, false, 500, 500));
        $this->assertSame(5.0, $this->calculator->calculate(Provinces::SOUTH, self::FEES, false, 499.99, 500));
    }

    public function testNoThreshold(): void
    {
        $this->assertSame(5.0, $this->calculator->calculate(Provinces::SOUTH, self::FEES, false, 9999, null));
        $this->assertSame(5.0, $this->calculator->calculate(Provinces::SOUTH, self::FEES, false, 9999, 0));
    }
}
