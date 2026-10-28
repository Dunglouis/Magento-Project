<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item;
use TechNest\CartSelect\Model\ItemSelection;

/**
 * A product added to the cart is ticked, also when it merges into an unticked line.
 */
class SelectAddedItem implements ObserverInterface
{
    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $item = $observer->getEvent()->getData('quote_item');
        if ($item instanceof Item) {
            /** @var Item|null $parent */
            $parent = $item->getParentItem();
            ($parent ?? $item)->setData(ItemSelection::FIELD, 1);
        }
    }
}
