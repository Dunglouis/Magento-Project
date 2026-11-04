<?php
declare(strict_types=1);

namespace TechNest\ProductSpecs\Plugin;

use Magento\Catalog\Block\Product\Compare\ListCompare;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use TechNest\ProductSpecs\Model\VariantValues;

/**
 * Compare page: configurable products show the RAM/storage/colour values of their variants,
 * and those rows are no longer dropped because the parent product has no value of its own
 */
class CompareVariantValues
{
    /**
     * @param VariantValues $variantValues
     */
    public function __construct(private readonly VariantValues $variantValues)
    {
    }

    /**
     * Keep the row when a configurable product offers the attribute through its variants
     *
     * @param ListCompare $subject
     * @param bool $result
     * @param Attribute $attribute
     * @return bool
     */
    public function afterHasAttributeValueForProducts(ListCompare $subject, $result, $attribute): bool
    {
        if ($result) {
            return true;
        }
        foreach ($subject->getItems() as $product) {
            if ($product instanceof Product
                && ($this->variantValues->get($product)[$attribute->getAttributeCode()] ?? '') !== ''
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Variant values in place of "N/A"
     *
     * @param ListCompare $subject
     * @param mixed $result
     * @param Product $product
     * @param Attribute $attribute
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetProductAttributeValue(ListCompare $subject, $result, $product, $attribute)
    {
        $value = $this->variantValues->get($product)[$attribute->getAttributeCode()] ?? '';
        return $value !== '' ? $value : $result;
    }
}
