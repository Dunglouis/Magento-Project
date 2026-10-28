<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Model;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * Which cart items are ticked (quote_item.tn_selected, 1 by default).
 * A child item (configurable/bundle option) follows its parent line.
 */
class ItemSelection
{
    public const FIELD = 'tn_selected';

    /**
     * Whether the cart line the item belongs to is ticked
     *
     * @param AbstractItem $item
     * @return bool
     */
    public function isSelected(AbstractItem $item): bool
    {
        /** @var AbstractItem|null $parent */
        $parent = $item->getParentItem();
        $line = $parent ?? $item;
        $value = $line->getData(self::FIELD);
        return $value === null || (int)$value === 1;
    }

    /**
     * Only the items of ticked lines, keys preserved
     *
     * @param AbstractItem[] $items
     * @return AbstractItem[]
     */
    public function filter(array $items): array
    {
        return array_filter($items, fn (AbstractItem $item): bool => $this->isSelected($item));
    }

    /**
     * Ids of the ticked cart lines
     *
     * @param Quote $quote
     * @return int[]
     */
    public function selectedLineIds(Quote $quote): array
    {
        $ids = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($this->isSelected($item)) {
                $ids[] = (int)$item->getId();
            }
        }
        return $ids;
    }

    /**
     * Cart lines that are not ticked
     *
     * @param Quote $quote
     * @return Quote\Item[]
     */
    public function unselectedLines(Quote $quote): array
    {
        return array_values(array_filter(
            $quote->getAllVisibleItems(),
            fn (Quote\Item $item): bool => !$this->isSelected($item)
        ));
    }
}
