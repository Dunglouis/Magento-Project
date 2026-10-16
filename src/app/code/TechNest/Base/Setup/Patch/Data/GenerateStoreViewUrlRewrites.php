<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\CmsUrlRewrite\Model\CmsPageUrlRewriteGenerator;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlPersistInterface;

/**
 * URL rewrites for the vi store view.
 *
 * Core only generates them for a new store via an adminhtml-area plugin
 * (CatalogUrlRewrite\Model\Category\Plugin\Store\View), which does not run in setup.
 */
class GenerateStoreViewUrlRewrites implements DataPatchInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param CategoryFactory $categoryFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param PageCollectionFactory $pageCollectionFactory
     * @param CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator
     * @param ProductUrlRewriteGenerator $productUrlRewriteGenerator
     * @param CmsPageUrlRewriteGenerator $cmsPageUrlRewriteGenerator
     * @param UrlPersistInterface $urlPersist
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryFactory $categoryFactory,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator,
        private readonly ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        private readonly CmsPageUrlRewriteGenerator $cmsPageUrlRewriteGenerator,
        private readonly UrlPersistInterface $urlPersist
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->storeManager->reinitStores();
        /** @var \Magento\Store\Model\Store $store */
        $store =$this->storeManager->getStore(CreateStoreViews::STORE_VI);
        $storeId = (int)$store->getId();

        $urls = [];
        $categories = $this->categoryFactory->create()
            ->getCategories((int)$store->getRootCategoryId(), 1, false, true, false)
            ->setStoreId($storeId);
        foreach ($categories as $category) {
            $category->setStoreId($storeId);
            $urls[] = $this->categoryUrlRewriteGenerator->generate($category);
        }
        $this->urlPersist->replace(array_merge([], ...$urls));

        $urls = [];
        $products = $this->productCollectionFactory->create()
            ->addCategoryIds()
            ->addAttributeToSelect(['name', 'url_path', 'url_key', 'visibility'])
            ->addStoreFilter($storeId);
        foreach ($products as $product) {
            $product->setStoreId($storeId);
            $urls[] = $this->productUrlRewriteGenerator->generate($product);
        }
        $this->urlPersist->replace(array_merge([], ...$urls));

        // CMS pages assigned to "All Store Views" get one rewrite per store view
        $pages = $this->pageCollectionFactory->create()->addStoreFilter($storeId);
        foreach ($pages as $page) {
            $page->setStoreId([$storeId]);
            $this->urlPersist->replace($this->cmsPageUrlRewriteGenerator->generate($page));
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
