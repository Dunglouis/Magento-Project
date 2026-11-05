<?php
declare(strict_types=1);

namespace TechNest\Search\Model\Autocomplete;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Search\Api\SearchInterface;
use Magento\Search\Model\Autocomplete\DataProviderInterface;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Up to LIMIT products (image, name, price, URL) for the header search autocomplete.
 * OpenSearch results come first (relevance on whole words); products whose name contains every
 * typed word fill the rest, so half-typed words ("sam", "zenb") already find products.
 */
class ProductSuggestions implements DataProviderInterface
{
    public const TYPE = 'product';
    public const LIMIT = 6;
    private const SEARCH_REQUEST = 'quick_search_container';

    /**
     * @param QueryFactory $queryFactory
     * @param SearchInterface $search
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Visibility $visibility
     * @param StoreManagerInterface $storeManager
     * @param ImageHelper $imageHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param ItemFactory $itemFactory
     */
    public function __construct(
        private readonly QueryFactory $queryFactory,
        private readonly SearchInterface $search,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly Visibility $visibility,
        private readonly StoreManagerInterface $storeManager,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ItemFactory $itemFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getItems()
    {
        $text = (string)$this->queryFactory->get()->getQueryText();
        $words = $this->searchWords($text);
        if (!$words) {
            return [];
        }

        $ids = $this->mergeIds($this->searchEngineIds($text), $this->nameMatchIds($words), self::LIMIT);
        if (!$ids) {
            return [];
        }

        $products = [];
        foreach ($this->loadProducts($ids) as $product) {
            $products[(int)$product->getId()] = $product;
        }

        $items = [];
        foreach ($ids as $id) {
            if (isset($products[$id])) {
                $items[] = $this->itemFactory->create($this->toItemData($products[$id], !$items));
            }
        }
        return $items;
    }

    /**
     * Distinct lower-case words of the typed text
     *
     * @param string $text
     * @return string[]
     */
    public function searchWords(string $text): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique($words));
    }

    /**
     * Search engine ids first, then name matches, without duplicates
     *
     * @param int[] $searchIds
     * @param int[] $nameIds
     * @param int $limit
     * @return int[]
     */
    public function mergeIds(array $searchIds, array $nameIds, int $limit): array
    {
        return array_slice(array_values(array_unique(array_merge($searchIds, $nameIds))), 0, $limit);
    }

    /**
     * LIKE pattern matching the word anywhere, with % and _ in the word taken literally
     *
     * @param string $word
     * @return string
     */
    public function likePattern(string $word): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word) . '%';
    }

    /**
     * Product ids from the store's search engine (OpenSearch), most relevant first
     *
     * @param string $text
     * @return int[]
     */
    private function searchEngineIds(string $text): array
    {
        // Same request the search results page builds (Fulltext collection): term + visibility, by relevance
        $this->searchCriteriaBuilder->addFilter(
            $this->filterBuilder->setField('search_term')->setValue($text)->create()
        );
        $this->searchCriteriaBuilder->addFilter(
            $this->filterBuilder->setField('visibility')->setValue($this->visibility->getVisibleInSearchIds())
                ->setConditionType('in')->create()
        );
        $criteria = $this->searchCriteriaBuilder->create()
            ->setRequestName(self::SEARCH_REQUEST)
            ->setSortOrders([
                $this->sortOrderBuilder->setField('relevance')->setDirection(SortOrder::SORT_DESC)->create(),
            ])
            ->setPageSize(self::LIMIT)
            ->setCurrentPage(0);

        $ids = [];
        foreach ($this->search->search($criteria)->getItems() as $document) {
            $ids[] = (int)$document->getId();
        }
        return $ids;
    }

    /**
     * Visible products whose store-view name contains every word; names starting with the first word first
     *
     * @param string[] $words
     * @return int[]
     */
    private function nameMatchIds(array $words): array
    {
        $collection = $this->baseCollection()->addAttributeToSelect('name');
        foreach ($words as $word) {
            $collection->addAttributeToFilter('name', ['like' => $this->likePattern($word)]);
        }
        $collection->setPageSize(self::LIMIT * 3)->setCurPage(1);

        $first = $words[0];
        $products = iterator_to_array($collection);
        usort($products, static function (Product $a, Product $b) use ($first): int {
            $aStarts = str_starts_with(mb_strtolower((string)$a->getName()), $first);
            $bStarts = str_starts_with(mb_strtolower((string)$b->getName()), $first);
            return $bStarts <=> $aStarts ?: strcasecmp((string)$a->getName(), (string)$b->getName());
        });
        return array_map(static fn (Product $product): int => (int)$product->getId(), $products);
    }

    /**
     * Products to show, with name, thumbnail, minimal price and URL
     *
     * @param int[] $ids
     * @return iterable<Product>
     */
    private function loadProducts(array $ids): iterable
    {
        return $this->baseCollection()
            ->addIdFilter($ids)
            ->addAttributeToSelect(['name', 'thumbnail', 'small_image'])
            ->addMinimalPrice()
            ->addUrlRewrite();
    }

    /**
     * Enabled products of the current store that can be found by search
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function baseCollection()
    {
        return $this->productCollectionFactory->create()
            ->setStoreId((int)$this->storeManager->getStore()->getId())
            ->addStoreFilter()
            ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
            ->setVisibility($this->visibility->getVisibleInSearchIds());
    }

    /**
     * Autocomplete row: title (product name), url, image, price in the store's currency
     *
     * @param Product $product
     * @param bool $isFirst
     * @return array<string,mixed>
     */
    private function toItemData(Product $product, bool $isFirst): array
    {
        $price = $product->getData('minimal_price') ?? $product->getData('min_price') ?? $product->getPrice();
        return [
            'type' => self::TYPE,
            'title' => (string)$product->getName(),
            'url' => $product->getProductUrl(),
            'image' => $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl(),
            'price' => $this->priceCurrency->convertAndFormat((float)$price, false),
            'heading' => $isFirst ? (string)__('Suggested products') : '',
            'row_class' => 'tn-qs-product' . ($isFirst ? ' tn-qs-product--first' : ''),
        ];
    }
}
