<?php
declare(strict_types=1);

namespace TechNest\Warranty\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TechNest\Warranty\Model\CoverPeriod;

class CoverPeriodTest extends TestCase
{
    /**
     * @return array<string,array{string,int,string}>
     */
    public static function periods(): array
    {
        return [
            '12 months' => ['2026-10-29', 12, '2027-10-28'],
            '1 month' => ['2026-01-15', 1, '2026-02-14'],
            'first of month' => ['2026-03-01', 6, '2026-08-31'],
            '31 Jan + 1 month: up to end of Feb' => ['2026-01-31', 1, '2026-02-28'],
            '31 Jan + 1 month in a leap year' => ['2028-01-31', 1, '2028-02-29'],
            '31 Aug + 1 month: up to 30 Sep' => ['2026-08-31', 1, '2026-09-30'],
            '31 Jan + 2 months' => ['2026-01-31', 2, '2026-03-30'],
            '29 Feb + 12 months' => ['2028-02-29', 12, '2029-02-28'],
            'start time ignored' => ['2026-10-29 23:10:00', 24, '2028-10-28'],
        ];
    }

    #[DataProvider('periods')]
    public function testLastDay(string $start, int $months, string $expected): void
    {
        self::assertSame($expected, (new CoverPeriod())->lastDay($start, $months));
    }

    public function testDaysLeftCountsTheLastDay(): void
    {
        $period = new CoverPeriod();
        self::assertSame(1, $period->daysLeft('2026-10-29', '2026-10-29'));
        self::assertSame(31, $period->daysLeft('2026-11-28', '2026-10-29'));
        self::assertSame(0, $period->daysLeft('2026-10-28', '2026-10-29'));
    }
}
