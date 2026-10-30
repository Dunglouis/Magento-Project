<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Psr\Log\LoggerInterface;
use TechNest\Warranty\Api\Data\WarrantyInterface;
use TechNest\Warranty\Api\WarrantyRepositoryInterface;
use TechNest\Warranty\Model\ResourceModel\Warranty as WarrantyResource;
use TechNest\Warranty\Setup\Patch\Data\AddWarrantyMonthsAttribute;

/**
 * Warranties of an order: one serial number per unit of every shipped product
 * (variants of configurable products and items of bundles count, their parents do not).
 *
 * A virtual item with "warranty_months" (the extended warranty packages) adds its months to every
 * warranty of the order; other virtual items get no warranty.
 */
class OrderWarranties
{
    /**
     * @param WarrantyRepositoryInterface $warrantyRepository
     * @param WarrantyFactory $warrantyFactory
     * @param WarrantyResource $warrantyResource
     * @param ProductResource $productResource
     * @param TimezoneInterface $timezone
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly WarrantyRepositoryInterface $warrantyRepository,
        private readonly WarrantyFactory $warrantyFactory,
        private readonly WarrantyResource $warrantyResource,
        private readonly ProductResource $productResource,
        private readonly TimezoneInterface $timezone,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create the warranties of a placed order (does nothing when the order already has some)
     *
     * @param Order $order
     * @return int Warranties created
     */
    public function issue(Order $order): int
    {
        $orderId = (int)$order->getId();
        if (!$orderId || $this->warrantyResource->hasOrder($orderId)) {
            return 0;
        }

        $units = [];
        $extraMonths = 0;
        foreach ($order->getAllItems() as $item) {
            /** @var OrderItem $item */
            if ($item->getChildrenItems()) {
                continue;
            }
            $months = $this->productMonths((int)$item->getProductId(), (int)$order->getStoreId());
            $qty = (int)$item->getQtyOrdered();
            if ($item->getIsVirtual()) {
                $extraMonths += $months * $qty;
                continue;
            }
            $units[] = [$item, $months ?: $this->config->getDefaultMonths(), $qty];
        }

        $startsAt = $this->timezone->date(new \DateTime((string)($order->getCreatedAt() ?: 'now')))->format('Y-m-d');
        $created = 0;
        foreach ($units as [$item, $months, $qty]) {
            for ($i = 0; $i < $qty; $i++) {
                $warranty = $this->warrantyFactory->create();
                $warranty->setOrderId($orderId)
                    ->setOrderIncrementId((string)$order->getIncrementId())
                    ->setSku((string)$item->getSku())
                    ->setProductName((string)$item->getName())
                    ->setCustomerId($order->getCustomerId() ? (int)$order->getCustomerId() : null)
                    ->setCustomerEmail((string)$order->getCustomerEmail())
                    ->setCustomerName(trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()))
                    ->setStartsAt($startsAt)
                    ->setMonths($months + $extraMonths)
                    ->setStatus(WarrantyInterface::STATUS_ACTIVE);
                try {
                    $this->warrantyRepository->save($warranty);
                    $created++;
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        'TechNest warranty for order %s, SKU %s not created: %s',
                        $order->getIncrementId(),
                        $item->getSku(),
                        $e->getMessage()
                    ));
                }
            }
        }
        return $created;
    }

    /**
     * Void the warranties of a cancelled order
     *
     * @param Order $order
     * @return int Warranties voided
     */
    public function void(Order $order): int
    {
        return $order->getId()
            ? $this->warrantyResource->setOrderStatus((int)$order->getId(), WarrantyInterface::STATUS_VOID)
            : 0;
    }

    /**
     * Product's own cover length, 0 when not set
     *
     * @param int $productId
     * @param int $storeId
     * @return int
     */
    private function productMonths(int $productId, int $storeId): int
    {
        if (!$productId) {
            return 0;
        }
        $value = $this->productResource->getAttributeRawValue(
            $productId,
            AddWarrantyMonthsAttribute::ATTRIBUTE_CODE,
            $storeId
        );
        return is_scalar($value) ? max(0, (int)$value) : 0;
    }
}
