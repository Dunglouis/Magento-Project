<?php
declare(strict_types=1);

namespace TechNest\Automation\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use TechNest\Base\Setup\Patch\Data\AddCustomOrderStatuses;

/**
 * Bank transfer orders still "Awaiting transfer confirmation" after the waiting time: cancelled with a comment
 * (cancelling returns the stock, and TechNest_Automation voids their warranties)
 */
class StaleTransfers
{
    /**
     * @param CollectionFactory $orderCollectionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CollectionFactory $orderCollectionFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Orders placed before this moment have waited too long
     *
     * @param \DateTimeImmutable $now
     * @param int $days
     * @return string UTC, Y-m-d H:i:s (like sales_order.created_at)
     */
    public function cutoff(\DateTimeImmutable $now, int $days): string
    {
        return $now->setTimezone(new \DateTimeZone('UTC'))->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
    }

    /**
     * Orders waiting for a transfer for more than the given number of days
     *
     * @param int $days
     * @return Order[]
     */
    public function find(int $days): array
    {
        /** @var Order[] $orders */
        $orders = $this->orderCollectionFactory->create()
            ->addFieldToFilter('state', Order::STATE_NEW)
            ->addFieldToFilter('status', AddCustomOrderStatuses::STATUS_AWAITING_TRANSFER)
            ->addFieldToFilter('created_at', ['lt' => $this->cutoff(new \DateTimeImmutable(), $days)])
            ->setOrder('entity_id', 'ASC')
            ->getItems();
        return $orders;
    }

    /**
     * Cancel the stale orders
     *
     * @param int $days
     * @param bool $dryRun Only list them
     * @return string[] Order numbers cancelled (or that would be)
     */
    public function cancel(int $days, bool $dryRun = false): array
    {
        $done = [];
        foreach ($this->find($days) as $order) {
            if (!$order->canCancel()) {
                continue;
            }
            if (!$dryRun) {
                try {
                    $order->cancel();
                    $order->addCommentToStatusHistory(
                        (string)__('Cancelled automatically: no bank transfer received within %1 days.', $days)
                    );
                    $this->orderRepository->save($order);
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        'TechNest: could not cancel unpaid order %s: %s',
                        $order->getIncrementId(),
                        $e->getMessage()
                    ));
                    continue;
                }
            }
            $done[] = (string)$order->getIncrementId();
        }
        return $done;
    }
}
