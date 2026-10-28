<?php
declare(strict_types=1);

namespace TechNest\CartSelect\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use TechNest\CartSelect\Model\ItemSelection;

/**
 * Cart page settings for js/cart-select: which lines are ticked and where to save changes.
 */
class CartSelection implements ArgumentInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param ItemSelection $itemSelection
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ItemSelection $itemSelection,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Widget options
     *
     * @return array<string,mixed>
     */
    public function getConfig(): array
    {
        $selected = [];
        foreach ($this->checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            $selected[(string)$item->getId()] = $this->itemSelection->isSelected($item);
        }
        return [
            'selectUrl' => $this->urlBuilder->getUrl('technest_cartselect/cart/select'),
            'selected' => (object)$selected,
            'selectAllLabel' => (string)__('Select all'),
            'selectItemLabel' => (string)__('Select %1'),
            'errorMessage' => (string)__('The selection could not be saved. Please reload the page and try again.'),
        ];
    }
}
