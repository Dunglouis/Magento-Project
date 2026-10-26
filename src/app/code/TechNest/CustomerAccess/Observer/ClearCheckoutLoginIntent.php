<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Url\DecoderInterface;
use TechNest\CustomerAccess\Model\CheckoutLoginIntent;

/**
 * Sign-in / registration opened with ?tn_checkout=1 (from checkout or its sign-in popup), or from the header
 * link on a cart page with items, starts the "return to checkout" intent; opened from any other page it is
 * dropped, so that sign-in ends on the home page. Other uncached pages drop it too.
 */
class ClearCheckoutLoginIntent implements ObserverInterface
{
    /**
     * @param CheckoutLoginIntent $checkoutLoginIntent
     * @param CheckoutSession $checkoutSession
     * @param DecoderInterface $urlDecoder
     */
    public function __construct(
        private readonly CheckoutLoginIntent $checkoutLoginIntent,
        private readonly CheckoutSession $checkoutSession,
        private readonly DecoderInterface $urlDecoder
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        /** @var HttpRequest $request */
        $request = $observer->getData('request');
        $action = strtolower((string)$request->getFullActionName()); // e.g. "loginPost" comes in camel case

        if (in_array($action, CheckoutLoginIntent::ENTRY_ACTIONS, true)) {
            if ($request->getParam(CheckoutLoginIntent::PARAM) || $this->isFromFilledCart($request)) {
                $this->checkoutLoginIntent->start();
            } else {
                $this->checkoutLoginIntent->clear();
            }
            return;
        }
        if (!in_array($action, CheckoutLoginIntent::KEEP_ON_ACTIONS, true)) {
            $this->checkoutLoginIntent->clear();
        }
    }

    /**
     * Whether the header sign-in link was clicked on the cart page while the cart has items
     *
     * @param HttpRequest $request
     * @return bool
     */
    private function isFromFilledCart(HttpRequest $request): bool
    {
        $referer = (string)$request->getParam(CustomerUrl::REFERER_QUERY_PARAM_NAME);
        if ($referer === '' || !$this->checkoutLoginIntent->isCartUrl((string)$this->urlDecoder->decode($referer))) {
            return false;
        }
        return (bool)$this->checkoutSession->getQuote()->hasItems();
    }
}
