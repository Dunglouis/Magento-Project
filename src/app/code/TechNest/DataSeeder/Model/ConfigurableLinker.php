<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as OptionsFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

/**
 * Sets the configurable attributes and child links of a configurable product.
 */
class ConfigurableLinker
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param OptionsFactory $optionsFactory
     * @param EavConfig $eavConfig
     * @param AttributeOptionResolver $optionResolver
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OptionsFactory $optionsFactory,
        private readonly EavConfig $eavConfig,
        private readonly AttributeOptionResolver $optionResolver
    ) {
    }

    /**
     * Link the variants (ProductDataMapper::mapVariants output, already saved) to the parent.
     *
     * @param string $parentSku
     * @param string[] $attributeCodes
     * @param array<int,array<string,mixed>> $variants
     * @return void
     * @throws LocalizedException
     */
    public function link(string $parentSku, array $attributeCodes, array $variants): void
    {
        $attributesData = [];
        foreach (array_values($attributeCodes) as $position => $code) {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
            $values = [];
            foreach ($variants as $variant) {
                $optionId = $this->optionResolver->getOptionId($code, (string)$variant['attributes'][$code]);
                $values[$optionId] = ['value_index' => $optionId];
            }
            $attributesData[] = [
                'attribute_id' => $attribute->getId(),
                'code' => $code,
                'label' => $attribute->getDefaultFrontendLabel(),
                'position' => $position,
                'values' => array_values($values),
            ];
        }

        /** @var Product $product */
        $product = $this->productRepository->get($parentSku, true, Store::DEFAULT_STORE_ID, true);
        /** @var ProductExtensionInterface $extension */
        $extension = $product->getExtensionAttributes();
        $extension->setConfigurableProductOptions($this->optionsFactory->create($attributesData));
        $extension->setConfigurableProductLinks(array_map(
            fn (array $variant): int => (int)$this->productRepository->get($variant['sku'])->getId(),
            $variants
        ));
        $product->setExtensionAttributes($extension);
        $this->productRepository->save($product);
    }
}
