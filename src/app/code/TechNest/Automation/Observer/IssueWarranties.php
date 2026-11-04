<?php
declare(strict_types=1);

namespace TechNest\Automation\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use TechNest\Warranty\Model\OrderWarranties;

/**
 * sales_model_service_quote_submit_success: one warranty per unit of the order.
 * Never breaks checkout: errors are only logged.
 */
class IssueWarranties implements ObserverInterface
{
    /**
     * @param OrderWarranties $orderWarranties
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderWarranties $orderWarranties,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order instanceof Order) {
            return;
        }
        try {
            $this->orderWarranties->issue($order);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'TechNest: warranties of order %s not created: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }
}
