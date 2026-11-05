<?php
declare(strict_types=1);

namespace TechNest\Installment\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use TechNest\Installment\Model\Calculator;

class CalculatorTest extends TestCase
{
    /**
     * @var Calculator
     */
    private Calculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new Calculator();
    }

    public function testFlatInterestPlans(): void
    {
        $plans = $this->calculator->calculate(1200.0, [12 => 1.0, 3 => 0.0, 6 => 0.0]);

        $this->assertSame([3, 6, 12], array_column($plans, 'months'), 'sorted by term');
        $this->assertSame(400.0, $plans[0]['monthly']);
        $this->assertSame(1200.0, $plans[0]['total']);
        $this->assertSame(0.0, $plans[0]['interest']);
        $this->assertSame(200.0, $plans[1]['monthly']);
        // 1200 / 12 + 1% of 1200 = 100 + 12
        $this->assertSame(112.0, $plans[2]['monthly']);
        $this->assertSame(1344.0, $plans[2]['total']);
        $this->assertSame(144.0, $plans[2]['interest']);
    }

    public function testRoundsToCurrencyPrecision(): void
    {
        $usd = $this->calculator->calculate(1599.0, [3 => 0.0]);
        $this->assertSame(533.0, $usd[0]['monthly']);

        $usd = $this->calculator->calculate(879.99, [6 => 0.0, 12 => 1.0]);
        $this->assertSame(146.67, $usd[0]['monthly']);
        $this->assertSame(879.99, $usd[0]['total'], 'a 0% plan costs exactly the price');
        $this->assertSame(0.0, $usd[0]['interest']);
        // 879.99 * 1.12 = 985.5888
        $this->assertSame(985.59, $usd[1]['total']);
        $this->assertSame(82.13, $usd[1]['monthly']);

        $vnd = $this->calculator->calculate(39975000.0, [6 => 0.0, 12 => 1.0], 0);
        $this->assertSame(6662500.0, $vnd[0]['monthly']);
        $this->assertSame(3731000.0, $vnd[1]['monthly']);
    }

    public function testNoPlansForZeroPriceOrBadTerms(): void
    {
        $this->assertSame([], $this->calculator->calculate(0.0, [3 => 0.0]));
        $this->assertSame([], $this->calculator->calculate(500.0, [0 => 1.0]));
    }

    public function testNegativeRateCountsAsZero(): void
    {
        $plans = $this->calculator->calculate(300.0, [3 => -2.0]);
        $this->assertSame(100.0, $plans[0]['monthly']);
    }

    public function testLowestMonthly(): void
    {
        $plans = $this->calculator->calculate(1200.0, [3 => 0.0, 6 => 0.0, 12 => 1.0]);
        $this->assertSame(112.0, $this->calculator->lowestMonthly($plans));
        $this->assertNull($this->calculator->lowestMonthly([]));
    }
}
