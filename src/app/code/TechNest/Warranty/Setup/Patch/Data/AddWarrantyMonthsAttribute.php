<?php
declare(strict_types=1);

namespace TechNest\Warranty\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;
use TechNest\Base\Setup\Patch\Data\CreateStoreViews;

/**
 * Product attribute "warranty_months" in the "Product Details" group of every product attribute set
 * (sets created later from the Default skeleton get it too). On the extended warranty packages
 * (virtual products) it is the number of months the package adds.
 */
class AddWarrantyMonthsAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'warranty_months';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->storeManager->reinitStores();
        $viId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(Product::ENTITY, self::ATTRIBUTE_CODE, [
            'type' => 'int',
            'label' => 'Warranty (months)',
            'input' => 'text',
            'frontend_class' => 'validate-digits',
            'required' => false,
            'user_defined' => true,
            'global' => Attribute::SCOPE_GLOBAL,
            'visible_on_front' => true,
            'used_in_product_listing' => false,
            'is_used_in_grid' => true,
            'is_visible_in_grid' => false,
            'is_filterable_in_grid' => true,
            'apply_to' => '',
            'sort_order' => 120,
            'note' => 'Empty: default from Stores > Configuration > TechNest > Warranty. '
                . 'Extended warranty packages: months added.',
        ]);
        $attributeId = (int)$eavSetup->getAttributeId(Product::ENTITY, self::ATTRIBUTE_CODE);
        $this->moduleDataSetup->getConnection()->insertOnDuplicate(
            $this->moduleDataSetup->getTable('eav_attribute_label'),
            ['attribute_id' => $attributeId, 'store_id' => $viId, 'value' => 'Bảo hành (tháng)'],
            ['value']
        );

        foreach ($eavSetup->getAllAttributeSetIds(Product::ENTITY) as $setId) {
            $eavSetup->addAttributeToGroup(Product::ENTITY, (int)$setId, 'Product Details', self::ATTRIBUTE_CODE, 120);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [CreateStoreViews::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
