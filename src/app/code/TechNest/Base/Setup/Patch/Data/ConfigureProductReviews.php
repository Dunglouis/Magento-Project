<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\ResourceModel\Rating as RatingResource;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Product reviews use one 1-5 star criterion, "Rating" ("Đánh giá" in vi), on both store views;
 * the sample criteria Quality/Value/Price stay off.
 */
class ConfigureProductReviews implements DataPatchInterface
{
    private const RATING_CODE = 'Rating';
    private const UNUSED_CODES = ['Quality', 'Value', 'Price'];

    /**
     * @param RatingFactory $ratingFactory
     * @param RatingResource $ratingResource
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly RatingFactory $ratingFactory,
        private readonly RatingResource $ratingResource,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->storeManager->reinitStores();
        $enId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_EN)->getId();
        $viId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();

        $rating = $this->ratingFactory->create();
        $this->ratingResource->load($rating, self::RATING_CODE, 'rating_code');
        if ($rating->getId()) {
            $rating->setIsActive(1)
                ->setStores([Store::DEFAULT_STORE_ID, $enId, $viId])
                ->setRatingCodes([$enId => 'Rating', $viId => 'Đánh giá']);
            $this->ratingResource->save($rating);
        }

        foreach (self::UNUSED_CODES as $code) {
            $unused = $this->ratingFactory->create();
            $this->ratingResource->load($unused, $code, 'rating_code');
            if ($unused->getId()) {
                $unused->setIsActive(0)->setStores([]);
                $this->ratingResource->save($unused);
            }
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
