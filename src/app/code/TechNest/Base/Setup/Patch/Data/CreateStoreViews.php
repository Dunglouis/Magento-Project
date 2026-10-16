<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ResourceModel\Store as StoreResource;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreFactory;

/**
 * Two store views on the default website/store: en (English) and vi (Tiếng Việt).
 */
class CreateStoreViews implements DataPatchInterface
{
    public const STORE_EN = 'en';
    public const STORE_VI = 'vi';

    /**
     * @param StoreFactory $storeFactory
     * @param StoreResource $storeResource
     */
    public function __construct(
        private readonly StoreFactory $storeFactory,
        private readonly StoreResource $storeResource
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $default = $this->loadStore('default');
        if ($default->getId()) {
            $default->setCode(self::STORE_EN)->setName('English');
            $this->storeResource->save($default);
        }

        $vi = $this->loadStore(self::STORE_VI);
        if (!$vi->getId()) {
            $en = $this->loadStore(self::STORE_EN);
            $vi->setData([
                'code' => self::STORE_VI,
                'name' => 'Tiếng Việt',
                'website_id' => $en->getWebsiteId(),
                'group_id' => $en->getGroupId(),
                'sort_order' => 10,
                'is_active' => 1,
            ]);
            // Store::afterSave() dispatches store_add -> sales sequences + search index for the new store
            $this->storeResource->save($vi);
        }

        return $this;
    }

    /**
     * Load a store view by its code (empty model if it does not exist).
     *
     * @param string $code
     * @return Store
     */
    private function loadStore(string $code): Store
    {
        $store = $this->storeFactory->create();
        $this->storeResource->load($store, $code, 'code');
        return $store;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
