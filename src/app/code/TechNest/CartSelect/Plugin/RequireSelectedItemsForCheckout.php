<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Plugin;

use Magento\Checkout\Controller\Index\Index as CheckoutIndex;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use TechNest\CartSelect\Model\ItemSelection;

/**
 * Opening the checkout with nothing ticked sends the customer back to the cart.
 */
class RequireSelectedItemsForCheckout
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param ItemSelection $itemSelection
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ItemSelection $itemSelection,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    /**
     * Redirect to the cart when the cart has lines but none is ticked
     *
     * @param CheckoutIndex $subject
     * @param callable $proceed
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(CheckoutIndex $subject, callable $proceed)
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote->hasItems() && !$this->itemSelection->selectedLineIds($quote)) {
            $this->messageManager->addNoticeMessage(__('Please select at least one product to check out.'));
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }
        return $proceed();
    }
}
