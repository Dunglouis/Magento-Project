<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Plugin;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\TotalsInterface;
use Magento\Quote\Model\Quote;
use TechNest\CartSelect\Model\ItemSelection;

/**
 * Cart totals API (cart summary, checkout order summary): list and count only the ticked lines.
 */
class TotalsItemsOfSelectedLines
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param ItemSelection $itemSelection
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ItemSelection $itemSelection
    ) {
    }

    /**
     * Keep the ticked lines in the totals items and items_qty
     *
     * @param CartTotalRepositoryInterface $subject
     * @param TotalsInterface $result
     * @param int $cartId
     * @return TotalsInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(CartTotalRepositoryInterface $subject, TotalsInterface $result, $cartId): TotalsInterface
    {
        /** @var Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);
        $selectedIds = $this->itemSelection->selectedLineIds($quote);
        $items = array_values(array_filter(
            $result->getItems() ?? [],
            static fn ($item): bool => in_array((int)$item->getItemId(), $selectedIds, true)
        ));
        $result->setItems($items);
        $result->setItemsQty(array_sum(array_map(static fn ($item): float => (float)$item->getQty(), $items)));
        return $result;
    }
}
