<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Plugin;

use Magento\Quote\Model\Quote\Address;
use TechNest\CartSelect\Model\ItemSelection;

/**
 * Address items feed every totals collector (subtotal, discounts, shipping rates, free shipping threshold):
 * leaving out the unticked lines makes the cart totals and the checkout cover only the ticked ones.
 */
class TotalsOfSelectedItems
{
    /**
     * @param ItemSelection $itemSelection
     */
    public function __construct(
        private readonly ItemSelection $itemSelection
    ) {
    }

    /**
     * Drop the items of unticked lines
     *
     * @param Address $subject
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem[] $result
     * @return \Magento\Quote\Model\Quote\Item\AbstractItem[]
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetAllItems(Address $subject, array $result): array
    {
        return array_values($this->itemSelection->filter($result));
    }
}
