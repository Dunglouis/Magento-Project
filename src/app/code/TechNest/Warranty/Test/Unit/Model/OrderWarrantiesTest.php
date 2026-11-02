<?php
declare(strict_types=1);

namespace TechNest\Warranty\Test\Unit\Model;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TechNest\Warranty\Api\Data\WarrantyInterface;
use TechNest\Warranty\Api\WarrantyRepositoryInterface;
use TechNest\Warranty\Model\Config;
use TechNest\Warranty\Model\OrderWarranties;
use TechNest\Warranty\Model\ResourceModel\Warranty as WarrantyResource;
use TechNest\Warranty\Model\Warranty;
use TechNest\Warranty\Model\WarrantyFactory;

class OrderWarrantiesTest extends TestCase
{
    /**
     * @var WarrantyInterface[]
     */
    private array $saved = [];

    /**
     * Product ID => warranty_months
     */
    private const MONTHS = [
        10 => 0,   // configurable parent
        11 => 24,  // its variant (laptop)
        20 => 0,   // mouse: default cover
        30 => 0,   // bundle parent
        31 => 0,   // bundle item: PC
        32 => 12,  // bundle item: extended warranty package (virtual)
    ];

    public function testOneWarrantyPerShippedUnitWithExtensionMonths(): void
    {
        $laptopParent = $this->item(10, 'TN-LAP', 'Laptop', 1);
        $laptop = $this->item(11, 'TN-LAP-16-512', 'Laptop 16GB', 1);
        $laptopParent->method('getChildrenItems')->willReturn([$laptop]);
        $mouse = $this->item(20, 'TN-MOUSE', 'Mouse', 2);
        $bundle = $this->item(30, 'TN-BD', 'Bundle', 1);
        $pc = $this->item(31, 'TN-PC', 'PC', 1);
        $package = $this->item(32, 'TN-WR-EXT-12M', 'Extended warranty', 1, true);
        $bundle->method('getChildrenItems')->willReturn([$pc, $package]);

        $created = $this->issuer(hasOrder: false)
            ->issue($this->order([$laptopParent, $laptop, $mouse, $bundle, $pc, $package]));

        self::assertSame(4, $created);
        $bySku = [];
        foreach ($this->saved as $warranty) {
            $bySku[$warranty->getSku()][] = $warranty->getMonths();
            self::assertSame(77, $warranty->getOrderId());
            self::assertSame('000000077', $warranty->getOrderIncrementId());
            self::assertSame(5, $warranty->getCustomerId());
            self::assertSame('An Nguyen', $warranty->getCustomerName());
            self::assertSame('2026-10-29', $warranty->getStartsAt());
            self::assertSame(WarrantyInterface::STATUS_ACTIVE, $warranty->getStatus());
        }
        // The 12-month package extends every warranty of the order
        self::assertSame(['TN-LAP-16-512' => [36], 'TN-MOUSE' => [18, 18], 'TN-PC' => [18]], $bySku);
    }

    public function testOrderWithWarrantiesIsSkipped(): void
    {
        $created = $this->issuer(hasOrder: true)->issue($this->order([$this->item(20, 'TN-MOUSE', 'Mouse', 1)]));

        self::assertSame(0, $created);
        self::assertSame([], $this->saved);
    }

    /**
     * @param bool $hasOrder
     * @return OrderWarranties
     */
    private function issuer(bool $hasOrder): OrderWarranties
    {
        $repository = $this->createStub(WarrantyRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (WarrantyInterface $warranty) {
            $this->saved[] = $warranty;
            return $warranty;
        });
        $factory = $this->createStub(WarrantyFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn () => (new \ReflectionClass(Warranty::class))->newInstanceWithoutConstructor()
        );
        $resource = $this->createStub(WarrantyResource::class);
        $resource->method('hasOrder')->willReturn($hasOrder);
        $productResource = $this->createStub(ProductResource::class);
        $productResource->method('getAttributeRawValue')
            ->willReturnCallback(fn (int $id) => (string)self::MONTHS[$id]);
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('date')->willReturn(new \DateTime('2026-10-29 10:00:00'));
        $config = $this->createStub(Config::class);
        $config->method('getDefaultMonths')->willReturn(6);

        return new OrderWarranties(
            $repository,
            $factory,
            $resource,
            $productResource,
            $timezone,
            $config,
            $this->createStub(LoggerInterface::class)
        );
    }

    /**
     * @param OrderItem[] $items
     * @return Order
     */
    private function order(array $items): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(77);
        $order->method('getIncrementId')->willReturn('000000077');
        $order->method('getStoreId')->willReturn(1);
        $order->method('getCreatedAt')->willReturn('2026-10-29 03:00:00');
        $order->method('getCustomerId')->willReturn(5);
        $order->method('getCustomerEmail')->willReturn('an@example.com');
        $order->method('getCustomerFirstname')->willReturn('An');
        $order->method('getCustomerLastname')->willReturn('Nguyen');
        $order->method('getAllItems')->willReturn($items);
        return $order;
    }

    /**
     * @param int $productId
     * @param string $sku
     * @param string $name
     * @param int $qty
     * @param bool $virtual
     * @return OrderItem&\PHPUnit\Framework\MockObject\Stub
     */
    private function item(int $productId, string $sku, string $name, int $qty, bool $virtual = false): OrderItem
    {
        $item = $this->createStub(OrderItem::class);
        $item->method('getProductId')->willReturn($productId);
        $item->method('getSku')->willReturn($sku);
        $item->method('getName')->willReturn($name);
        $item->method('getQtyOrdered')->willReturn((float)$qty);
        $item->method('getIsVirtual')->willReturn($virtual);
        return $item;
    }
}
