<?php
declare(strict_types=1);

namespace TechNest\Automation\Model;

use Magento\Catalog\Model\Indexer\Product\Price\Processor as PriceIndexer;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Indexer\CacheContextFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;

/**
 * Ended flash sales: special price and sale dates are removed once special_to_date has passed,
 * prices are reindexed and the cached pages of those products (product, category, home page shelf) refreshed,
 * so no "Sale" badge or countdown outlives the sale.
 */
class FlashSaleCleanup
{
    /**
     * @param CollectionFactory $productCollectionFactory
     * @param ProductAction $productAction
     * @param PriceIndexer $priceIndexer
     * @param CacheContextFactory $cacheContextFactory
     * @param EventManager $eventManager
     * @param CacheInterface $cache
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly ProductAction $productAction,
        private readonly PriceIndexer $priceIndexer,
        private readonly CacheContextFactory $cacheContextFactory,
        private readonly EventManager $eventManager,
        private readonly CacheInterface $cache,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Clean up the ended sales
     *
     * @param bool $dryRun Only list the products
     * @return array<int,string> Product ID => SKU cleaned up (or that would be)
     */
    public function run(bool $dryRun = false): array
    {
        $products = $this->productCollectionFactory->create()
            ->setStoreId(Store::DEFAULT_STORE_ID)
            ->addAttributeToFilter('special_to_date', ['lt' => $this->timezone->date()->format('Y-m-d')]);
        $skus = [];
        foreach ($products as $product) {
            $skus[(int)$product->getId()] = (string)$product->getSku();
        }
        if ($dryRun || !$skus) {
            return $skus;
        }

        $ids = array_keys($skus);
        $this->productAction->updateAttributes(
            $ids,
            ['special_price' => null, 'special_from_date' => null, 'special_to_date' => null],
            Store::DEFAULT_STORE_ID
        );
        $this->priceIndexer->reindexList($ids);

        $cacheContext = $this->cacheContextFactory->create();
        $cacheContext->registerEntities(Product::CACHE_TAG, $ids);
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $cacheContext]);
        $this->cache->clean($cacheContext->getIdentities());
        return $skus;
    }
}
