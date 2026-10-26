<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Test\Unit\Model\Step;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\ResourceModel\Rating as RatingResource;
use Magento\Review\Model\ResourceModel\Rating\Option\CollectionFactory as RatingOptionCollectionFactory;
use Magento\Review\Model\ResourceModel\Review as ReviewResource;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\Step\Reviews;

class ReviewsTest extends TestCase
{
    private const STORES = ['en' => 1, 'vi' => 2];

    /**
     * @var Reviews
     */
    private Reviews $step;

    protected function setUp(): void
    {
        $this->step = new Reviews(
            $this->createStub(SeedData::class),
            $this->createStub(ProductResource::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(ReviewFactory::class),
            $this->createStub(ReviewResource::class),
            $this->createStub(ReviewCollectionFactory::class),
            $this->createStub(RatingFactory::class),
            $this->createStub(RatingResource::class),
            $this->createStub(RatingOptionCollectionFactory::class)
        );
    }

    /**
     * @param string $store
     * @param string $nickname
     * @param int $rating
     * @return array<string,mixed>
     */
    private static function review(string $store, string $nickname, int $rating = 5): array
    {
        return ['store' => $store, 'nickname' => $nickname, 'rating' => $rating, 'title' => 'T', 'detail' => 'D'];
    }

    public function testNewReviewsBecomeRowsForTheirStore(): void
    {
        $entries = [['sku' => 'TN-A', 'reviews' => [self::review('en', 'Ann'), self::review('vi', 'Bình', 4)]]];

        $rows = $this->step->pendingReviews($entries, ['TN-A' => 10], self::STORES, []);

        self::assertCount(2, $rows);
        self::assertSame(['product_id' => 10, 'store_id' => 2, 'nickname' => 'Bình', 'rating' => 4,
            'title' => 'T', 'detail' => 'D'], $rows[1]);
    }

    public function testExistingReviewsAreNotAddedAgain(): void
    {
        $entries = [['sku' => 'TN-A', 'reviews' => [self::review('en', 'Ann'), self::review('vi', 'Bình')]]];
        $existing = [$this->step->key(10, 1, 'Ann', 'T') => true];

        $rows = $this->step->pendingReviews($entries, ['TN-A' => 10], self::STORES, $existing);

        self::assertSame(['Bình'], array_column($rows, 'nickname'));
    }

    public function testDuplicatesInSeedDataAreAddedOnce(): void
    {
        $entries = [['sku' => 'TN-A', 'reviews' => [self::review('en', 'Ann'), self::review('en', 'Ann')]]];

        self::assertCount(1, $this->step->pendingReviews($entries, ['TN-A' => 10], self::STORES, []));
    }

    public function testUnknownProductsAndStoresAreSkipped(): void
    {
        $entries = [
            ['sku' => 'TN-MISSING', 'reviews' => [self::review('en', 'Ann')]],
            ['sku' => 'TN-A', 'reviews' => [self::review('fr', 'Zoé')]],
        ];

        self::assertSame([], $this->step->pendingReviews($entries, ['TN-A' => 10], self::STORES, []));
    }

    public function testRatingIsClampedToOneToFiveStars(): void
    {
        $entries = [['sku' => 'TN-A', 'reviews' => [self::review('en', 'Ann', 9), self::review('vi', 'Bình', 0)]]];

        $rows = $this->step->pendingReviews($entries, ['TN-A' => 10], self::STORES, []);

        self::assertSame([5, 1], array_column($rows, 'rating'));
    }
}
