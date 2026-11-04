<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

use Magento\Bundle\Api\Data\LinkInterfaceFactory;
use Magento\Bundle\Api\Data\OptionInterfaceFactory;
use Magento\Bundle\Api\ProductOptionRepositoryInterface;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Catalog\Api\Data\ProductLinkExtensionInterface;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

/**
 * Sets bundle options and grouped "associated" links on already saved bundle/grouped products.
 */
class CompositeLinker
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ProductOptionRepositoryInterface $bundleOptionRepository
     * @param OptionInterfaceFactory $bundleOptionFactory
     * @param LinkInterfaceFactory $bundleLinkFactory
     * @param ProductLinkInterfaceFactory $productLinkFactory
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductOptionRepositoryInterface $bundleOptionRepository,
        private readonly OptionInterfaceFactory $bundleOptionFactory,
        private readonly LinkInterfaceFactory $bundleLinkFactory,
        private readonly ProductLinkInterfaceFactory $productLinkFactory
    ) {
    }

    /**
     * Bundle options: one option per seed group, first item is the default; existing options are reused by title.
     *
     * @param string $sku
     * @param array<int,array<string,mixed>> $groups [{title, required, items: [sku, ...]}]
     * @return void
     * @throws LocalizedException
     */
    public function linkBundle(string $sku, array $groups): void
    {
        /** @var Product $product */
        $product = $this->productRepository->get($sku, true, Store::DEFAULT_STORE_ID, true);
        $existing = [];
        foreach ($this->bundleOptionRepository->getList($sku) as $option) {
            $existing[(string)$option->getTitle()] = (int)$option->getOptionId();
        }

        $options = [];
        foreach (array_values($groups) as $position => $group) {
            $links = [];
            foreach (array_values($group['items']) as $i => $itemSku) {
                $links[] = $this->bundleLinkFactory->create()
                    ->setSku($itemSku)
                    ->setQty(1)
                    ->setCanChangeQuantity(0)
                    ->setIsDefault($i === 0);
            }
            $option = $this->bundleOptionFactory->create()
                ->setTitle($group['title'])
                ->setType('select')
                ->setRequired((bool)$group['required'])
                ->setPosition($position + 1)
                ->setSku($sku)
                ->setProductLinks($links);
            if (isset($existing[$group['title']])) {
                $option->setOptionId($existing[$group['title']]);
            }
            $options[] = $option;
        }

        /** @var ProductExtensionInterface $extension */
        $extension = $product->getExtensionAttributes();
        $extension->setBundleProductOptions($options);
        $product->setExtensionAttributes($extension);
        $this->productRepository->save($product);
    }

    /**
     * Grouped products: "associated" links with a default qty of 1.
     *
     * @param string $sku
     * @param string[] $itemSkus
     * @return void
     * @throws LocalizedException
     */
    public function linkGrouped(string $sku, array $itemSkus): void
    {
        /** @var Product $product */
        $product = $this->productRepository->get($sku, true, Store::DEFAULT_STORE_ID, true);
        $links = [];
        foreach (array_values($itemSkus) as $position => $itemSku) {
            $link = $this->productLinkFactory->create()
                ->setSku($sku)
                ->setLinkType('associated')
                ->setLinkedProductSku($itemSku)
                ->setLinkedProductType('simple')
                ->setPosition($position);
            /** @var ProductLinkExtensionInterface $extension */
            $extension = $link->getExtensionAttributes();
            $extension->setQty(1);
            $link->setExtensionAttributes($extension);
            $links[] = $link;
        }
        $product->setProductLinks($links);
        $this->productRepository->save($product);
    }
}
