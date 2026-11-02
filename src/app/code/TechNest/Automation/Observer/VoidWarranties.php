<?php
declare(strict_types=1);

namespace TechNest\Automation\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use TechNest\Warranty\Model\OrderWarranties;

/**
 * order_cancel_after: the warranties of a cancelled order become void
 */
class VoidWarranties implements ObserverInterface
{
    /**
     * @param OrderWarranties $orderWarranties
     */
    public function __construct(private readonly OrderWarranties $orderWarranties)
    {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if ($order instanceof Order) {
            $this->orderWarranties->void($order);
        }
    }
}
