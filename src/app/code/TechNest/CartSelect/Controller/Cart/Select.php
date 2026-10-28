<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use TechNest\CartSelect\Model\ItemSelection;

/**
 * POST technest_cartselect/cart/select: tick or untick cart lines (item_ids[] or all=1, selected=0|1),
 * then recollect totals. Answers with how many lines are ticked.
 */
class Select implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $cartRepository
     * @param ItemSelection $itemSelection
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ItemSelection $itemSelection
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): Json
    {
        $quote = $this->checkoutSession->getQuote();
        $selected = (int)(bool)$this->request->getParam('selected');
        $all = (bool)$this->request->getParam('all');
        $ids = array_map('intval', (array)$this->request->getParam('item_ids', []));

        $changed = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            if (($all || in_array((int)$item->getId(), $ids, true))
                && (int)$this->itemSelection->isSelected($item) !== $selected
            ) {
                $item->setData(ItemSelection::FIELD, $selected);
                $changed = true;
            }
        }
        if ($changed) {
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $this->cartRepository->save($quote);
        }

        return $this->jsonFactory->create()->setData([
            'success' => true,
            'selected_count' => count($this->itemSelection->selectedLineIds($quote)),
            'items_count' => count($quote->getAllVisibleItems()),
        ]);
    }
}
