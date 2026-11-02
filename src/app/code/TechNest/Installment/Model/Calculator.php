<?php
declare(strict_types=1);

namespace TechNest\Installment\Model;

/**
 * Flat-interest instalment plans: total = price + price * rate per month * months, paid in equal months.
 * view/frontend/web/js/installment.js repeats this formula when the price changes on the page.
 */
class Calculator
{
    /**
     * Plans for a price, in the price's currency
     *
     * @param float $price
     * @param float[] $rates Months => interest in % per month
     * @param int $precision Decimals of the currency (0 for VND)
     * @return array<int, array{months: int, rate: float, monthly: float, total: float, interest: float}>
     */
    public function calculate(float $price, array $rates, int $precision = 2): array
    {
        if ($price <= 0) {
            return [];
        }
        ksort($rates);
        $plans = [];
        foreach ($rates as $months => $rate) {
            $months = (int)$months;
            if ($months <= 0) {
                continue;
            }
            $rate = max(0.0, (float)$rate);
            // Total first, so a 0% plan costs exactly the price; the monthly amount is rounded
            $total = round($price * (1 + $rate / 100 * $months), $precision);
            $monthly = round($total / $months, $precision);
            $plans[] = [
                'months' => $months,
                'rate' => $rate,
                'monthly' => $monthly,
                'total' => $total,
                'interest' => round(max(0.0, $total - $price), $precision),
            ];
        }
        return $plans;
    }

    /**
     * Lowest monthly payment among the plans, null without plans
     *
     * @param mixed[][] $plans Plans from calculate()
     * @return float|null
     */
    public function lowestMonthly(array $plans): ?float
    {
        return $plans ? (float)min(array_column($plans, 'monthly')) : null;
    }
}
