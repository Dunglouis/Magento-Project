<?php
declare(strict_types=1);

namespace TechNest\ProductSpecs\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use TechNest\ProductSpecs\Model\SpecSheet;
use TechNest\ProductSpecs\Model\VariantValues;

/**
 * Specification sheet of the product page ("Specifications" tab), grouped by the attribute groups of its set.
 * A configurable product lists the values of its variants ("16 GB, 32 GB").
 */
class Specs implements ArgumentInterface
{
    /**
     * Products without their own warranty_months get the default cover of TechNest_Warranty
     */
    private const WARRANTY_ATTRIBUTE = 'warranty_months';
    private const WARRANTY_DEFAULT_PATH = 'technest_warranty/general/default_months';

    /**
     * @param SpecSheet $specSheet
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param VariantValues $variantValues
     */
    public function __construct(
        private readonly SpecSheet $specSheet,
        private readonly GroupCollectionFactory $groupCollectionFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly VariantValues $variantValues
    ) {
    }

    /**
     * Groups of rows (label, value) shown on the storefront
     *
     * @param Product $product
     * @return array<int, array<string, mixed>> Each with label and rows (code, label, value)
     */
    public function getGroups(Product $product): array
    {
        $setId = (int)$product->getAttributeSetId();
        $groupNames = $this->getGroupNames($setId);
        $variantValues = $this->variantValues->get($product);

        $rows = [];
        /** @var Attribute $attribute */
        foreach ($product->getTypeInstance()->getSetAttributes($product) as $attribute) {
            $info = $attribute->getData('attribute_set_info')[$setId] ?? null;
            if (!$info || !$attribute->getIsVisibleOnFront() || $attribute->getFrontendInput() === 'price') {
                continue;
            }
            $code = (string)$attribute->getAttributeCode();
            if ($code === self::WARRANTY_ATTRIBUTE) {
                // A grouped product only lists other products, which carry their own warranty
                if ($product->getTypeId() !== Grouped::TYPE_CODE) {
                    $rows[] = $this->getWarrantyRow($product);
                }
                continue;
            }
            $rows[] = [
                'group_id' => (int)$info['group_id'],
                'group' => $groupNames[(int)$info['group_id']] ?? '',
                'group_sort' => (int)$info['group_sort'],
                'sort' => (int)$info['sort'],
                'code' => $code,
                'label' => (string)$attribute->getStoreLabel(),
                'value' => $variantValues[$code] ?? $this->getValue($product, $attribute),
            ];
        }
        return $this->specSheet->build($rows);
    }

    /**
     * Value as shown on the storefront (option labels, Yes/No...)
     *
     * @param Product $product
     * @param Attribute $attribute
     * @return string
     */
    private function getValue(Product $product, Attribute $attribute): string
    {
        $value = $product->getData($attribute->getAttributeCode());
        if ($value === null || $value === '' || $value === false) {
            return '';
        }
        $text = $attribute->getFrontend()->getValue($product);
        return is_scalar($text) || $text instanceof \Stringable ? (string)$text : '';
    }

    /**
     * Warranty in a group of its own after the specifications: "Warranty period: 12 months"
     *
     * @param Product $product
     * @return array<string, mixed>
     */
    private function getWarrantyRow(Product $product): array
    {
        $months = (int)$product->getData(self::WARRANTY_ATTRIBUTE)
            ?: (int)$this->scopeConfig->getValue(self::WARRANTY_DEFAULT_PATH);
        return [
            'group_id' => -1,
            'group' => 'Warranty',
            'group_sort' => PHP_INT_MAX,
            'sort' => 0,
            'code' => self::WARRANTY_ATTRIBUTE,
            'label' => (string)__('Warranty period'),
            'value' => $months > 0 ? (string)__('%1 months', $months) : '',
        ];
    }

    /**
     * Attribute group names of a set
     *
     * @param int $setId
     * @return array<int, string> Group id => name
     */
    private function getGroupNames(int $setId): array
    {
        $names = [];
        foreach ($this->groupCollectionFactory->create()->setAttributeSetFilter($setId) as $group) {
            $names[(int)$group->getId()] = (string)$group->getAttributeGroupName();
        }
        return $names;
    }
}
