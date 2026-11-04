<?php
declare(strict_types=1);

namespace TechNest\Shipping\Model;

/**
 * Delivery fee of an order for its zone, or zero when free shipping applies
 */
class FeeCalculator
{
    /**
     * Fee in the base currency
     *
     * @param string|null $zone One of the Provinces zones, null when the province is unknown
     * @param float[] $fees Zone => fee, plus 'default' for an unknown province
     * @param bool $freeShipping A free shipping coupon covers the whole order
     * @param float $orderAmount Subtotal after discount compared with the threshold
     * @param float|null $freeThreshold Free shipping from this amount, null when not used
     * @return float
     */
    public function calculate(
        ?string $zone,
        array $fees,
        bool $freeShipping,
        float $orderAmount,
        ?float $freeThreshold
    ): float {
        if ($freeShipping || ($freeThreshold !== null && $freeThreshold > 0 && $orderAmount >= $freeThreshold)) {
            return 0.0;
        }
        return max(0.0, (float)($fees[$zone ?? 'default'] ?? $fees['default'] ?? 0.0));
    }
}
