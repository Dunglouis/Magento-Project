<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

/**
 * Category URL path (e.g. "accessories/mice") -> category id.
 */
class CategoryResolver
{
    /**
     * @var array<string, int>|null
     */
    private ?array $ids = null;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Category id for a URL path.
     *
     * @param string $urlPath
     * @return int
     * @throws LocalizedException
     */
    public function getId(string $urlPath): int
    {
        if ($this->ids === null) {
            $this->ids = [];
            $collection = $this->collectionFactory->create()
                ->setStoreId(Store::DEFAULT_STORE_ID)
                ->addAttributeToSelect('url_path')
                ->addAttributeToFilter('level', ['gteq' => 2]);
            foreach ($collection as $category) {
                $this->ids[(string)$category->getUrlPath()] = (int)$category->getId();
            }
        }
        if (!isset($this->ids[$urlPath])) {
            throw new LocalizedException(__('Unknown category "%1": run the categories step first', $urlPath));
        }
        return $this->ids[$urlPath];
    }
}
