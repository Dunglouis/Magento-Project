<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\Catalog\Model\Product\Link;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Link as LinkResource;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\DataSeeder\Console\Command\CleanupLegacy;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * "Related products": each visible product links to the products of its deepest category,
 * closest in price first. Existing related links of seeded products are replaced.
 */
class Related implements StepInterface
{
    private const MAX_LINKS = 8;

    /**
     * @var array<int,int>|null category id => level, loaded once
     */
    private ?array $levels = null;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param Visibility $visibility
     * @param LinkResource $linkResource
     */
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly Visibility $visibility,
        private readonly LinkResource $linkResource
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Related product links (same category, closest price)';
    }

    /**
     * @inheritdoc
     */
    public function execute(OutputInterface $output): void
    {
        $products = $this->productCollectionFactory->create()
            ->setStoreId(Store::DEFAULT_STORE_ID)
            ->addAttributeToSelect('price')
            ->addAttributeToFilter('sku', ['like' => CleanupLegacy::SKU_PREFIX . '%'])
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addCategoryIds();

        $groups = [];
        $prices = [];
        foreach ($products as $product) {
            $categoryId = $this->deepestCategoryId($product->getCategoryIds());
            if ($categoryId !== null) {
                $groups[$categoryId][] = (int)$product->getId();
                $prices[(int)$product->getId()] = (float)$product->getData('price');
            }
        }

        $linked = 0;
        foreach ($groups as $productIds) {
            foreach ($productIds as $productId) {
                $this->linkResource->saveProductLinks(
                    $productId,
                    $this->buildLinks($productId, $productIds, $prices),
                    Link::LINK_TYPE_RELATED
                );
                $linked++;
            }
        }
        $output->writeln(sprintf('  related links set for %d products in %d categories', $linked, count($groups)));
    }

    /**
     * Up to MAX_LINKS other products of the group, closest price first
     *
     * @param int $productId
     * @param int[] $groupIds
     * @param array<int,float> $prices
     * @return array<int,array{position: int}>
     */
    public function buildLinks(int $productId, array $groupIds, array $prices): array
    {
        $others = array_values(array_diff($groupIds, [$productId]));
        $distance = static fn (int $id): float => abs($prices[$id] - $prices[$productId]);
        usort($others, static fn (int $a, int $b): int => $distance($a) <=> $distance($b) ?: $a <=> $b);

        $links = [];
        foreach (array_slice($others, 0, self::MAX_LINKS) as $position => $id) {
            $links[$id] = ['position' => $position + 1];
        }
        return $links;
    }

    /**
     * Deepest (highest level) category among the product's categories
     *
     * @param array<int|string> $categoryIds
     * @return int|null
     */
    private function deepestCategoryId(array $categoryIds): ?int
    {
        if ($this->levels === null) {
            $this->levels = [];
            foreach ($this->categoryCollectionFactory->create() as $category) {
                $this->levels[(int)$category->getId()] = (int)$category->getLevel();
            }
        }
        $levels = $this->levels;

        $deepest = null;
        foreach ($categoryIds as $id) {
            $id = (int)$id;
            if ($deepest === null || ($levels[$id] ?? 0) > ($levels[$deepest] ?? 0)) {
                $deepest = $id;
            }
        }
        return $deepest;
    }
}
