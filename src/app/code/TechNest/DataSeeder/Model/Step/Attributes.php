<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\Catalog\Api\AttributeSetManagementInterface;
use Magento\Catalog\Api\ProductAttributeOptionManagementInterface;
use Magento\Catalog\Api\ProductAttributeOptionUpdateInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Eav\Api\Data\AttributeSetInterfaceFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\Base\Setup\Patch\Data\CreateStoreViews;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * Creates or updates product attributes, their options and the TechNest attribute sets
 * from data/attributes.json. Vietnamese labels are stored on the vi store view.
 */
class Attributes implements StepInterface
{
    public const GROUP_NAME = 'Specifications';
    private const GROUP_SORT = 15;

    /**
     * @param SeedData $seedData
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     * @param EavConfig $eavConfig
     * @param StoreManagerInterface $storeManager
     * @param ProductAttributeOptionManagementInterface $optionManagement
     * @param ProductAttributeOptionUpdateInterface $optionUpdate
     * @param AttributeOptionInterfaceFactory $optionFactory
     * @param AttributeOptionLabelInterfaceFactory $optionLabelFactory
     * @param AttributeSetManagementInterface $attributeSetManagement
     * @param AttributeSetInterfaceFactory $attributeSetFactory
     */
    public function __construct(
        private readonly SeedData $seedData,
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly EavConfig $eavConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductAttributeOptionManagementInterface $optionManagement,
        private readonly ProductAttributeOptionUpdateInterface $optionUpdate,
        private readonly AttributeOptionInterfaceFactory $optionFactory,
        private readonly AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        private readonly AttributeSetManagementInterface $attributeSetManagement,
        private readonly AttributeSetInterfaceFactory $attributeSetFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Product attributes, options and attribute sets';
    }

    /**
     * @inheritdoc
     */
    public function execute(OutputInterface $output): void
    {
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);
        $viStoreId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();
        $data = $this->seedData->getAttributes();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach ($data['attributes'] as $code => $spec) {
            $isNew = !$eavSetup->getAttributeId(Product::ENTITY, $code);
            $this->saveAttribute($eavSetup, $code, $spec, $viStoreId);
            $added = $this->saveOptions($code, $spec['options'] ?? [], $viStoreId);
            $output->writeln(sprintf('  %s %s%s', $isNew ? '+' : '=', $code, $added ? " (+$added options)" : ''));
        }

        $defaultSetId = (int)$eavSetup->getDefaultAttributeSetId(Product::ENTITY);
        foreach ($data['sets'] as $setName => $codes) {
            $isNew = !$eavSetup->getAttributeSet(Product::ENTITY, $setName);
            $setId = $this->ensureSet($eavSetup, $setName, $defaultSetId);
            $eavSetup->addAttributeGroup(Product::ENTITY, $setId, self::GROUP_NAME, self::GROUP_SORT);
            foreach (array_values($codes) as $sort => $code) {
                $eavSetup->addAttributeToGroup(Product::ENTITY, $setId, self::GROUP_NAME, $code, $sort + 1);
            }
            $output->writeln(sprintf('  %s set "%s" (%d attributes)', $isNew ? '+' : '=', $setName, count($codes)));
        }

        $this->eavConfig->clear();
    }

    /**
     * Create the attribute if missing, then (re)apply storefront flags and the vi label.
     *
     * @param EavSetup $eavSetup
     * @param string $code
     * @param array<mixed> $spec
     * @param int $viStoreId
     * @return void
     */
    private function saveAttribute(EavSetup $eavSetup, string $code, array $spec, int $viStoreId): void
    {
        $isSelect = $spec['input'] === 'select';
        if (!$eavSetup->getAttributeId(Product::ENTITY, $code)) {
            $eavSetup->addAttribute(Product::ENTITY, $code, [
                'type' => $isSelect ? 'int' : 'varchar',
                'input' => $spec['input'],
                'label' => $spec['label']['en'],
                // Selects become configurable options, so they must be global
                'global' => $isSelect ? ScopedAttributeInterface::SCOPE_GLOBAL : ScopedAttributeInterface::SCOPE_STORE,
                'required' => false,
                'user_defined' => true,
                'visible' => true,
            ]);
        }

        $filterable = $isSelect && !empty($spec['filterable']);
        $eavSetup->updateAttribute(Product::ENTITY, $code, [
            'frontend_label' => $spec['label']['en'],
            'is_visible_on_front' => 1,
            'is_comparable' => 1,
            'is_searchable' => 1,
            'is_filterable' => $filterable ? 1 : 0,
            'is_filterable_in_search' => $filterable ? 1 : 0,
            'used_in_product_listing' => empty($spec['listing']) ? 0 : 1,
            // No default: otherwise every product saved without this attribute gets the default option
            'default_value' => '',
        ]);

        $connection = $this->moduleDataSetup->getConnection();
        $labelTable = $this->moduleDataSetup->getTable('eav_attribute_label');
        $attributeId = (int)$eavSetup->getAttributeId(Product::ENTITY, $code);
        $connection->delete($labelTable, ['attribute_id = ?' => $attributeId, 'store_id = ?' => $viStoreId]);
        $connection->insert($labelTable, [
            'attribute_id' => $attributeId,
            'store_id' => $viStoreId,
            'value' => $spec['label']['vi'],
        ]);
    }

    /**
     * Add missing options; refresh the vi label of existing ones.
     *
     * @param string $code
     * @param array<mixed> $options Plain strings, or {"en": ..., "vi": ...} pairs
     * @param int $viStoreId
     * @return int Number of options added
     */
    private function saveOptions(string $code, array $options, int $viStoreId): int
    {
        $existing = [];
        foreach ($options ? $this->optionManagement->getItems($code) : [] as $item) {
            if ((string)$item->getValue() !== '') {
                $existing[mb_strtolower((string)$item->getLabel())] = (int)$item->getValue();
            }
        }

        $added = 0;
        foreach (array_values($options) as $sort => $value) {
            $en = is_array($value) ? $value['en'] : $value;
            $vi = is_array($value) ? $value['vi'] : $value;
            $option = $this->optionFactory->create()
                ->setLabel($en)
                ->setSortOrder($sort + 1)
                ->setStoreLabels([$this->optionLabelFactory->create()->setStoreId($viStoreId)->setLabel($vi)]);

            $optionId = $existing[mb_strtolower($en)] ?? null;
            if ($optionId === null) {
                $this->optionManagement->add($code, $option);
                $added++;
            } else {
                $this->optionUpdate->update($code, $optionId, $option);
            }
        }
        return $added;
    }

    /**
     * Existing attribute set id, or a new set copied from the Default set.
     *
     * @param EavSetup $eavSetup
     * @param string $name
     * @param int $skeletonId
     * @return int
     */
    private function ensureSet(EavSetup $eavSetup, string $name, int $skeletonId): int
    {
        $setId = $eavSetup->getAttributeSet(Product::ENTITY, $name, 'attribute_set_id');
        if ($setId) {
            return (int)$setId;
        }
        $set = $this->attributeSetFactory->create()
            ->setAttributeSetName($name)
            ->setEntityTypeId((int)$eavSetup->getEntityTypeId(Product::ENTITY));
        return (int)$this->attributeSetManagement->create($set, $skeletonId)->getAttributeSetId();
    }
}
