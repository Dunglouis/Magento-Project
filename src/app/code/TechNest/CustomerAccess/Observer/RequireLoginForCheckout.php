<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Observer;

use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use TechNest\CustomerAccess\Model\CheckoutLoginIntent;

/**
 * Guests can fill a cart; opening checkout sends them to sign in / create an account,
 * then back to checkout (core merges the guest cart into the customer's cart on login).
 * Without this, core just bounces them to the cart with "Guest checkout is disabled."
 */
class RequireLoginForCheckout implements ObserverInterface
{
    /**
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param CheckoutHelper $checkoutHelper
     * @param ManagerInterface $messageManager
     * @param ActionFlag $actionFlag
     * @param RedirectInterface $redirect
     * @param CheckoutLoginIntent $checkoutLoginIntent
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly CheckoutHelper $checkoutHelper,
        private readonly ManagerInterface $messageManager,
        private readonly ActionFlag $actionFlag,
        private readonly RedirectInterface $redirect,
        private readonly CheckoutLoginIntent $checkoutLoginIntent
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        if ($this->customerSession->isLoggedIn()) {
            return;
        }
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->hasItems() || $this->checkoutHelper->isAllowedGuestCheckout($quote)) {
            return;
        }

        $this->messageManager->addNoticeMessage(
            __('Please sign in or create an account to place your order. Your cart has been saved.')
        );
        $this->checkoutLoginIntent->start();
        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, '1');
        $this->redirect->redirect(
            $observer->getData('controller_action')->getResponse(),
            'customer/account/login',
            ['_query' => [CheckoutLoginIntent::PARAM => 1]]
        );
    }
}
