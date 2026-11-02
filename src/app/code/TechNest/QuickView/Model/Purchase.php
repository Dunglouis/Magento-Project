<?php
declare(strict_types=1);

namespace TechNest\QuickView\Model;

/**
 * Whether the quick view can add the product to the cart directly, or must send the shopper to the product page
 */
class Purchase
{
    /**
     * Types bought without choosing anything
     */
    private const DIRECT_TYPES = ['simple', 'virtual'];

    /**
     * Add to cart from the quick view: a salable simple/virtual product without required custom options
     *
     * @param string $typeId
     * @param bool $isSalable
     * @param bool $hasRequiredOptions
     * @return bool
     */
    public function canAddDirectly(string $typeId, bool $isSalable, bool $hasRequiredOptions): bool
    {
        return $isSalable && !$hasRequiredOptions && in_array($typeId, self::DIRECT_TYPES, true);
    }
}
