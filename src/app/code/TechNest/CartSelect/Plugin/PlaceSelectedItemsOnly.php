<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Psr\Log\LoggerInterface;
use TechNest\CartSelect\Model\ItemSelection;

/**
 * Placing an order converts only the ticked lines. Unticked lines are taken out of the quote first
 * (the order is built from all quote items), then added to the customer's next cart, still unticked,
 * so they are waiting in the cart after the order. Checkout needs an account (guest checkout is off).
 */
class PlaceSelectedItemsOnly
{
    // Magento\Quote\Model\Quote\Address::CACHED_ITEMS_ALL is private
    private const ADDRESS_ITEMS_CACHE = 'cached_items_all';

    /**
     * @param ItemSelection $itemSelection
     * @param ProductRepositoryInterface $productRepository
     * @param CartManagementInterface $cartManagement
     * @param CartRepositoryInterface $cartRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ItemSelection $itemSelection,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Submit the ticked lines, keep the others in the cart
     *
     * @param QuoteManagement $subject
     * @param callable $proceed
     * @param Quote $quote
     * @param array<string,mixed> $orderData
     * @return mixed
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSubmit(QuoteManagement $subject, callable $proceed, Quote $quote, $orderData = [])
    {
        $unselected = $this->itemSelection->unselectedLines($quote);
        if (!$unselected || !$quote->getCustomerId()) {
            return $proceed($quote, $orderData);
        }
        if (count($unselected) === count($quote->getAllVisibleItems())) {
            throw new LocalizedException(__('Please select at least one product to check out.'));
        }

        $held = [];
        foreach ($unselected as $item) {
            $held[] = ['product_id' => (int)$item->getProductId(), 'request' => $item->getBuyRequest()];
            $quote->removeItem($item->getId());
        }
        $quote->getShippingAddress()->unsetData(self::ADDRESS_ITEMS_CACHE);
        $quote->getBillingAddress()->unsetData(self::ADDRESS_ITEMS_CACHE);
        $quote->setTotalsCollectedFlag(false)->collectTotals();

        try {
            $order = $proceed($quote, $orderData);
        } catch (\Throwable $e) {
            // Order failed: put the lines back where they were
            $this->addLines($quote, $held);
            $this->cartRepository->save($quote);
            throw $e;
        }

        try {
            $cartId = $this->cartManagement->createEmptyCartForCustomer((int)$quote->getCustomerId());
            /** @var Quote $nextCart */
            $nextCart = $this->cartRepository->get($cartId);
            $this->addLines($nextCart, $held);
            $this->cartRepository->save($nextCart);
        } catch (\Throwable $e) {
            // The order is placed; losing the unticked lines must not turn it into an error page
            $this->logger->error('TechNest_CartSelect: could not keep unticked cart lines', ['exception' => $e]);
        }
        return $order;
    }

    /**
     * Add products back as unticked lines
     *
     * @param Quote $quote
     * @param array<int,array<string,mixed>> $lines [{product_id: int, request: DataObject}]
     * @return void
     * @throws LocalizedException
     */
    private function addLines(Quote $quote, array $lines): void
    {
        foreach ($lines as $line) {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $this->productRepository->getById($line['product_id'], false, (int)$quote->getStoreId(), true);
            $item = $quote->addProduct($product, $line['request']);
            if (is_string($item)) {
                throw new LocalizedException(__($item));
            }
            $item->setData(ItemSelection::FIELD, 0);
        }
        $quote->setTotalsCollectedFlag(false)->collectTotals();
    }
}
