<?php
declare(strict_types=1);

namespace TechNest\ProductSpecs\Model;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * A configurable product has no value of its own for its option attributes (RAM, storage, colour):
 * list the values its variants offer instead, e.g. "16 GB, 32 GB"
 */
class VariantValues
{
    private const CACHE_KEY = '_technest_variant_values';

    /**
     * Values of the configurable attributes across the variants, in option order (cached per product)
     *
     * @param Product $product
     * @return array<string, string> Attribute code => "value, value"
     */
    public function get(Product $product): array
    {
        $type = $product->getTypeInstance();
        if (!$type instanceof Configurable) {
            return [];
        }
        $cached = $product->getData(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        $children = $type->getUsedProducts($product);
        $values = [];
        foreach ($type->getConfigurableAttributes($product) as $configurableAttribute) {
            $attribute = $configurableAttribute->getProductAttribute();
            $code = (string)$attribute->getAttributeCode();
            $used = [];
            /** @var Product $child */
            foreach ($children as $child) {
                $used[(string)$child->getData($code)] = true;
            }
            $labels = [];
            foreach ($attribute->getSource()->getAllOptions() as $option) {
                if ((string)$option['value'] !== '' && isset($used[(string)$option['value']])) {
                    $labels[] = (string)$option['label'];
                }
            }
            $values[$code] = implode(', ', $labels);
        }
        $product->setData(self::CACHE_KEY, $values);
        return $values;
    }
}
