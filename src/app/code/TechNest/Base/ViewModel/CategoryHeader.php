<?php
declare(strict_types=1);

namespace TechNest\Base\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Category page heading: "Name. Tagline" and a row of sibling/child categories with a product picture.
 */
class CategoryHeader implements ArgumentInterface
{
    private const MIN_NAV_LEVEL = 2; // level 2 = top menu categories (Laptops, Phones...)

    /**
     * @param LayerResolver $layerResolver
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ImageHelper $imageHelper
     * @param Visibility $visibility
     */
    public function __construct(
        private readonly LayerResolver $layerResolver,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ImageHelper $imageHelper,
        private readonly Visibility $visibility
    ) {
    }

    /**
     * Category being viewed
     *
     * @return Category|null
     */
    public function getCategory(): ?Category
    {
        $category = $this->layerResolver->get()->getCurrentCategory();
        return $category->getId() ? $category : null;
    }

    /**
     * Plain-text tagline stored in the category description
     *
     * @return string
     */
    public function getTagline(): string
    {
        return trim(strip_tags((string)$this->getCategory()?->getData('description')));
    }

    /**
     * Children of the current category, or its siblings when it has none
     *
     * @return array<int,array{name: string,url: string,image: string,current: bool}>
     */
    public function getNavItems(): array
    {
        $category = $this->getCategory();
        if (!$category) {
            return [];
        }
        $parent = $category->hasChildren() ? $category : $category->getParentCategory();
        if ((int)$parent->getLevel() < self::MIN_NAV_LEVEL) {
            return [];
        }

        $items = [];
        foreach ($parent->getChildrenCategories() as $child) {
            $items[] = [
                'name' => (string)$child->getName(),
                'url' => (string)$child->getUrl(),
                'image' => $this->getPictureUrl((int)$child->getId(), (int)$category->getStoreId()),
                'current' => (int)$child->getId() === (int)$category->getId(),
            ];
        }
        return $items;
    }

    /**
     * Small image of the first visible product in a category ('' when there is none)
     *
     * @param int $categoryId
     * @param int $storeId
     * @return string
     */
    private function getPictureUrl(int $categoryId, int $storeId): string
    {
        $product = $this->productCollectionFactory->create()
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addCategoriesFilter(['in' => [$categoryId]])
            ->addAttributeToSelect('small_image')
            ->addAttributeToFilter('small_image', ['nin' => ['', 'no_selection']])
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->setPageSize(1)
            ->getFirstItem();

        return $product instanceof Product && $product->getId()
            ? (string)$this->imageHelper->init($product, 'category_page_grid')->getUrl()
            : '';
    }
}
