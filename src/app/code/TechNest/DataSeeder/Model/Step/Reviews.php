<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\ResourceModel\Rating as RatingResource;
use Magento\Review\Model\ResourceModel\Rating\Option\CollectionFactory as RatingOptionCollectionFactory;
use Magento\Review\Model\ResourceModel\Review as ReviewResource;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * Approved sample reviews (en/vi) with a 1-5 star vote from data/reviews.json.
 * A review is identified by product + store + nickname + title, so re-runs only add missing ones.
 */
class Reviews implements StepInterface
{
    private const RATING_CODE = 'Rating';

    /**
     * @param SeedData $seedData
     * @param ProductResource $productResource
     * @param StoreManagerInterface $storeManager
     * @param ReviewFactory $reviewFactory
     * @param ReviewResource $reviewResource
     * @param ReviewCollectionFactory $reviewCollectionFactory
     * @param RatingFactory $ratingFactory
     * @param RatingResource $ratingResource
     * @param RatingOptionCollectionFactory $ratingOptionCollectionFactory
     */
    public function __construct(
        private readonly SeedData $seedData,
        private readonly ProductResource $productResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly ReviewFactory $reviewFactory,
        private readonly ReviewResource $reviewResource,
        private readonly ReviewCollectionFactory $reviewCollectionFactory,
        private readonly RatingFactory $ratingFactory,
        private readonly RatingResource $ratingResource,
        private readonly RatingOptionCollectionFactory $ratingOptionCollectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Sample product reviews with star ratings';
    }

    /**
     * @inheritdoc
     */
    public function execute(OutputInterface $output): void
    {
        $entries = $this->seedData->getReviews();
        $productIds = [];
        foreach ($entries as $entry) {
            $id = $this->productResource->getIdBySku($entry['sku']);
            if ($id) {
                $productIds[$entry['sku']] = (int)$id;
            } else {
                $output->writeln(sprintf('  <comment>skip %s: product not found</comment>', $entry['sku']));
            }
        }
        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeIds[$store->getCode()] = (int)$store->getId();
        }

        $pending = $this->pendingReviews($entries, $productIds, $storeIds, $this->existingKeys($productIds));
        [$ratingId, $optionIds] = $this->ratingOptions();
        $touched = [];
        foreach ($pending as $row) {
            $this->create($row, $ratingId, $optionIds);
            $touched[$row['product_id']] = true;
        }
        foreach (array_keys($touched) as $productId) {
            $summary = $this->reviewFactory->create();
            $summary->setEntityId($summary->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
                ->setEntityPkValue($productId)
                ->aggregate();
        }
        $output->writeln(sprintf(
            '  %d reviews added (%d already there)',
            count($pending),
            $this->countAll($entries) - count($pending)
        ));
    }

    /**
     * Reviews from the seed data that do not exist yet (unknown products or stores are skipped)
     *
     * @param array<int,array<string,mixed>> $entries
     * @param array<string,int> $productIds sku => product id
     * @param array<string,int> $storeIds store code => store id
     * @param array<string,bool> $existingKeys see key()
     * @return array<int,array<string,mixed>> rows: product_id, store_id, nickname, rating, title, detail
     */
    public function pendingReviews(array $entries, array $productIds, array $storeIds, array $existingKeys): array
    {
        $rows = [];
        foreach ($entries as $entry) {
            $productId = $productIds[$entry['sku']] ?? null;
            foreach ($productId ? $entry['reviews'] : [] as $review) {
                $storeId = $storeIds[$review['store']] ?? null;
                if ($storeId === null) {
                    continue;
                }
                $key = $this->key($productId, $storeId, $review['nickname'], $review['title']);
                if (isset($existingKeys[$key])) {
                    continue;
                }
                $existingKeys[$key] = true;
                $rows[] = [
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'nickname' => $review['nickname'],
                    'rating' => max(1, min(5, (int)$review['rating'])),
                    'title' => $review['title'],
                    'detail' => $review['detail'],
                ];
            }
        }
        return $rows;
    }

    /**
     * Identity of a review
     *
     * @param int $productId
     * @param int $storeId
     * @param string $nickname
     * @param string $title
     * @return string
     */
    public function key(int $productId, int $storeId, string $nickname, string $title): string
    {
        return $productId . '|' . $storeId . '|' . $nickname . '|' . $title;
    }

    /**
     * Keys of reviews already stored for the given products
     *
     * @param array<string,int> $productIds
     * @return array<string,bool>
     */
    private function existingKeys(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }
        $collection = $this->reviewCollectionFactory->create()
            ->addFieldToFilter(
                'main_table.entity_id',
                ['eq' => (int)$this->reviewFactory->create()->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE)]
            )
            ->addFieldToFilter('main_table.entity_pk_value', ['in' => array_values($productIds)]);
        $keys = [];
        foreach ($collection as $review) {
            $keys[$this->key(
                (int)$review->getEntityPkValue(),
                (int)$review->getData('store_id'),
                (string)$review->getNickname(),
                (string)$review->getTitle()
            )] = true;
        }
        return $keys;
    }

    /**
     * The "Rating" criterion id and its option ids by star value
     *
     * @return array{0: int, 1: array<int,int>}
     */
    private function ratingOptions(): array
    {
        $rating = $this->ratingFactory->create();
        $this->ratingResource->load($rating, self::RATING_CODE, 'rating_code');
        $ratingId = (int)$rating->getId();

        $optionIds = [];
        foreach ($this->ratingOptionCollectionFactory->create()->addRatingFilter($ratingId) as $option) {
            $optionIds[(int)$option->getValue()] = (int)$option->getId();
        }
        return [$ratingId, $optionIds];
    }

    /**
     * Save one approved review and its star vote
     *
     * @param array<string,mixed> $row
     * @param int $ratingId
     * @param array<int,int> $optionIds
     * @return void
     */
    private function create(array $row, int $ratingId, array $optionIds): void
    {
        $review = $this->reviewFactory->create();
        $review->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
            ->setEntityPkValue($row['product_id'])
            ->setStatusId(Review::STATUS_APPROVED)
            ->setNickname($row['nickname'])
            ->setTitle($row['title'])
            ->setDetail($row['detail'])
            ->setStoreId($row['store_id'])
            ->setStores([$row['store_id']]);
        $this->reviewResource->save($review);

        if ($ratingId && isset($optionIds[$row['rating']])) {
            $this->ratingFactory->create()
                ->setRatingId($ratingId)
                ->setReviewId($review->getId())
                ->addOptionVote($optionIds[$row['rating']], $row['product_id']);
        }
    }

    /**
     * Number of reviews in the seed data
     *
     * @param array<int,array<string,mixed>> $entries
     * @return int
     */
    private function countAll(array $entries): int
    {
        return array_sum(array_map(static fn (array $entry): int => count($entry['reviews']), $entries));
    }
}
