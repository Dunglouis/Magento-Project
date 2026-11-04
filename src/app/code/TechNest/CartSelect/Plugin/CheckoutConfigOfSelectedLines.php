<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Plugin;

use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use TechNest\CartSelect\Model\ItemSelection;

/**
 * Checkout page: the order summary item list (quoteItemData) shows only the ticked lines.
 */
class CheckoutConfigOfSelectedLines
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param ItemSelection $itemSelection
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ItemSelection $itemSelection
    ) {
    }

    /**
     * Filter quoteItemData by the ticked line ids
     *
     * @param DefaultConfigProvider $subject
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetConfig(DefaultConfigProvider $subject, array $result): array
    {
        if (!isset($result['quoteItemData']) || !is_array($result['quoteItemData'])) {
            return $result;
        }
        $selectedIds = $this->itemSelection->selectedLineIds($this->checkoutSession->getQuote());
        $result['quoteItemData'] = array_values(array_filter(
            $result['quoteItemData'],
            static fn (array $item): bool => in_array((int)($item['item_id'] ?? 0), $selectedIds, true)
        ));
        return $result;
    }
}
