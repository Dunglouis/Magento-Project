<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Free Shipping is also unlocked by the FREESHIP coupon, so its method name no longer mentions the $500 threshold.
 */
class RenameFreeShippingMethod implements DataPatchInterface
{
    /**
     * @param WriterInterface $configWriter
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->configWriter->save('carriers/freeshipping/name', 'Free shipping');

        $this->storeManager->reinitStores();
        $viId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();
        $this->configWriter->save(
            'carriers/freeshipping/name',
            'Miễn phí vận chuyển',
            ScopeInterface::SCOPE_STORES,
            $viId
        );

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
