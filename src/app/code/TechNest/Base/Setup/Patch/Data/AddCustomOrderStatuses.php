<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Shop order statuses on top of the core states:
 * bank transfer orders wait for the payment check ("new"), invoiced orders are packed ("processing"),
 * shipped orders are out for delivery and the admin marks them delivered ("complete").
 * The "new" state keeps "Pending" as default, so COD orders are unchanged.
 */
class AddCustomOrderStatuses implements DataPatchInterface
{
    public const STATUS_AWAITING_TRANSFER = 'awaiting_transfer';
    public const STATUS_PACKING = 'packing';
    public const STATUS_SHIPPING = 'shipping';
    public const STATUS_DELIVERED = 'delivered';

    /**
     * status => [state, default for the state, label en, label vi]
     */
    private const STATUSES = [
        self::STATUS_AWAITING_TRANSFER => [
            Order::STATE_NEW, false, 'Awaiting transfer confirmation', 'Chờ xác nhận chuyển khoản',
        ],
        self::STATUS_PACKING => [Order::STATE_PROCESSING, true, 'Packing', 'Đang đóng gói'],
        self::STATUS_SHIPPING => [Order::STATE_COMPLETE, true, 'Out for delivery', 'Đang giao'],
        self::STATUS_DELIVERED => [Order::STATE_COMPLETE, false, 'Delivered', 'Đã giao'],
    ];

    /**
     * @param StatusFactory $statusFactory
     * @param StatusResource $statusResource
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $configWriter
     */
    public function __construct(
        private readonly StatusFactory $statusFactory,
        private readonly StatusResource $statusResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly WriterInterface $configWriter
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->storeManager->reinitStores();
        $viId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();

        foreach (self::STATUSES as $code => [$state, $isDefault, $label, $labelVi]) {
            $status = $this->statusFactory->create();
            $this->statusResource->load($status, $code);
            $status->setData('status', $code)
                ->setData('label', $label)
                ->setData('store_labels', [$viId => $labelVi]);
            $this->statusResource->save($status);
            $this->statusResource->assignState($code, $state, $isDefault, true);
        }

        $this->configWriter->save('payment/banktransfer/order_status', self::STATUS_AWAITING_TRANSFER);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [ConfigureShippingAndPayment::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
