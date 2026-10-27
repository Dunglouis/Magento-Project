<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Shipping: Flat Rate $5 per order, free shipping from $500, no Table Rate.
 * Payment: Cash on Delivery and Bank Transfer for VN only (demo bank account), no Check/Money order.
 * Default scope holds the English texts, the vi store view overrides them in Vietnamese.
 */
class ConfigureShippingAndPayment implements DataPatchInterface
{
    private const DEFAULT_CONFIG = [
        'carriers/flatrate/active' => '1',
        'carriers/flatrate/type' => 'O',
        'carriers/flatrate/price' => '5',
        'carriers/flatrate/title' => 'Standard shipping',
        'carriers/flatrate/name' => 'Flat rate',
        'carriers/flatrate/sallowspecific' => '1',
        'carriers/flatrate/specificcountry' => 'VN',
        'carriers/flatrate/sort_order' => '20',
        'carriers/freeshipping/active' => '1',
        'carriers/freeshipping/free_shipping_subtotal' => '500',
        'carriers/freeshipping/title' => 'Free shipping',
        'carriers/freeshipping/name' => 'Orders from $500',
        'carriers/freeshipping/sallowspecific' => '1',
        'carriers/freeshipping/specificcountry' => 'VN',
        'carriers/freeshipping/sort_order' => '10',
        'carriers/tablerate/active' => '0',
        'payment/checkmo/active' => '0',
        'payment/checkmo/payable_to' => null,
        'payment/checkmo/mailing_address' => null,
        'payment/cashondelivery/active' => '1',
        'payment/cashondelivery/title' => 'Cash on Delivery',
        'payment/cashondelivery/instructions' => 'Pay in cash when the courier delivers your order.',
        'payment/cashondelivery/allowspecific' => '1',
        'payment/cashondelivery/specificcountry' => 'VN',
        'payment/cashondelivery/sort_order' => '10',
        'payment/banktransfer/active' => '1',
        'payment/banktransfer/title' => 'Bank Transfer',
        'payment/banktransfer/instructions' => "Please transfer the order total to:\n"
            . "Bank: Vietcombank\nAccount number: 0123456789\nAccount holder: CONG TY TECHNEST\n"
            . "Transfer note: your order number.\nWe ship once the payment is confirmed.",
        'payment/banktransfer/allowspecific' => '1',
        'payment/banktransfer/specificcountry' => 'VN',
        'payment/banktransfer/sort_order' => '20',
    ];

    private const VI_CONFIG = [
        'carriers/flatrate/title' => 'Giao hàng tiêu chuẩn',
        'carriers/flatrate/name' => 'Phí cố định',
        'carriers/freeshipping/title' => 'Miễn phí vận chuyển',
        'carriers/freeshipping/name' => 'Đơn từ 12.500.000₫',
        'payment/cashondelivery/title' => 'Thanh toán khi nhận hàng (COD)',
        'payment/cashondelivery/instructions' => 'Thanh toán tiền mặt khi nhân viên giao hàng tới.',
        'payment/banktransfer/title' => 'Chuyển khoản ngân hàng',
        'payment/banktransfer/instructions' => "Vui lòng chuyển khoản tổng tiền đơn hàng tới:\n"
            . "Ngân hàng: Vietcombank\nSố tài khoản: 0123456789\nChủ tài khoản: CONG TY TECHNEST\n"
            . "Nội dung: mã đơn hàng của bạn.\nĐơn hàng được giao sau khi xác nhận thanh toán.",
    ];

    /**
     * @param WriterInterface $configWriter
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        foreach (self::DEFAULT_CONFIG as $path => $value) {
            $this->configWriter->save($path, $value);
        }

        $this->storeManager->reinitStores();
        $viId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();
        foreach (self::VI_CONFIG as $path => $value) {
            $this->configWriter->save($path, $value, ScopeInterface::SCOPE_STORES, $viId);
        }

        // Rates typed in by hand during the Magento lessons; Table Rate is no longer used
        $connection = $this->resource->getConnection();
        $connection->delete($this->resource->getTableName('shipping_tablerate'));

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [ConfigureLocaleAndCurrency::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
