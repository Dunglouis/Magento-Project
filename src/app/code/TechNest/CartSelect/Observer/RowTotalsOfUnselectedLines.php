<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use TechNest\CartSelect\Model\ItemSelection;

/**
 * Unticked lines are left out of the totals collectors, so nothing refreshes their own row total
 * (e.g. after switching USD -> VND). Recalculate it for display in the cart; it is not added to any total.
 */
class RowTotalsOfUnselectedLines implements ObserverInterface
{
    private const CACHED_PRICES = ['converted_price', 'calculation_price', 'base_calculation_price'];

    /**
     * @param ItemSelection $itemSelection
     */
    public function __construct(
        private readonly ItemSelection $itemSelection
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        if (!$quote instanceof Quote) {
            return;
        }
        foreach ($this->itemSelection->unselectedLines($quote) as $line) {
            foreach (self::CACHED_PRICES as $key) {
                $line->unsetData($key);
            }
            $line->calcRowTotal();
            $line->setData('row_total_incl_tax', $line->getData('row_total'));
            $line->setData('base_row_total_incl_tax', $line->getData('base_row_total'));
        }
    }
}
