<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\Base\Setup\Patch\Data\CreateStoreViews;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * Creates or updates the category tree from data/categories.json.
 * English names on the default scope, Vietnamese names on the vi store view.
 */
class Categories implements StepInterface
{
    /**
     * @param SeedData $seedData
     * @param StoreManagerInterface $storeManager
     * @param CategoryFactory $categoryFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CategoryResource $categoryResource
     * @param CategoryUrlRewriteGenerator $urlRewriteGenerator
     * @param UrlPersistInterface $urlPersist
     */
    public function __construct(
        private readonly SeedData $seedData,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryResource $categoryResource,
        private readonly CategoryUrlRewriteGenerator $urlRewriteGenerator,
        private readonly UrlPersistInterface $urlPersist
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Category tree (en names, vi translations)';
    }

    /**
     * @inheritdoc
     */
    public function execute(OutputInterface $output): void
    {
        // Save on the default scope: values apply to all store views, URL rewrites are made for each one
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);

        /** @var Store $enStore */
        $enStore = $this->storeManager->getStore(CreateStoreViews::STORE_EN);
        $viStoreId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();

        $this->saveTree($this->seedData->getCategories(), (int)$enStore->getRootCategoryId(), $viStoreId, $output);
    }

    /**
     * Save one level of the tree, then recurse into children.
     *
     * @param array<mixed> $nodes
     * @param int $parentId
     * @param int $viStoreId
     * @param OutputInterface $output
     * @param string $pathPrefix
     * @return void
     */
    private function saveTree(
        array $nodes,
        int $parentId,
        int $viStoreId,
        OutputInterface $output,
        string $pathPrefix = ''
    ): void {
        foreach ($nodes as $position => $node) {
            $category = $this->findChild($parentId, $node['url_key']) ?? $this->categoryFactory->create();
            $isNew = !$category->getId();

            $category->setStoreId(Store::DEFAULT_STORE_ID)
                ->setParentId($parentId)
                ->setName($node['name']['en'])
                ->setUrlKey($node['url_key'])
                ->setIsActive(true)
                ->setIncludeInMenu(true)
                ->setIsAnchor(true)
                ->setPosition($position + 1);
            /** @var Category $category */
            $category = $this->categoryRepository->save($category);

            // Store-view value: only the name differs, URL keys stay English
            $category->setStoreId($viStoreId)->setName($node['name']['vi']);
            $this->categoryResource->saveAttribute($category, 'name');

            // Rebuild URL rewrites for every store view (core only does it for new/renamed categories)
            $category->setStoreId(Store::DEFAULT_STORE_ID);
            $this->urlPersist->replace($this->urlRewriteGenerator->generate($category));

            $path = $pathPrefix . $node['url_key'];
            $output->writeln(sprintf('  %s %s', $isNew ? '+' : '=', $path));
            $this->saveTree($node['children'] ?? [], (int)$category->getId(), $viStoreId, $output, $path . '/');
        }
    }

    /**
     * Existing child category with this URL key, if any.
     *
     * @param int $parentId
     * @param string $urlKey
     * @return Category|null
     */
    private function findChild(int $parentId, string $urlKey): ?Category
    {
        /** @var Category $category */
        $category = $this->categoryCollectionFactory->create()
            ->setStoreId(Store::DEFAULT_STORE_ID)
            ->addAttributeToFilter('url_key', $urlKey)
            ->addFieldToFilter('parent_id', $parentId)
            ->getFirstItem();
        return $category->getId() ? $category : null;
    }
}
