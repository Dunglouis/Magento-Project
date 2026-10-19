<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

/**
 * Adds downloaded images to a product's media gallery, once: products that already have images are left alone.
 */
class ProductImageAttacher
{
    private const MAIN_IMAGE_ROLES = ['image', 'small_image', 'thumbnail'];

    /**
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * Attach images; the first one becomes the base, small and thumbnail image.
     *
     * @param string $sku
     * @param string[] $files Absolute paths inside pub/media
     * @return int Number of images added (0 when the product already had images)
     * @throws LocalizedException
     */
    public function attach(string $sku, array $files): int
    {
        if ($files === []) {
            return 0;
        }
        /** @var Product $product */
        $product = $this->productRepository->get($sku, true, Store::DEFAULT_STORE_ID, true);
        if (!empty($product->getMediaGalleryEntries())) {
            return 0;
        }
        foreach (array_values($files) as $i => $file) {
            $product->addImageToMediaGallery($file, $i === 0 ? self::MAIN_IMAGE_ROLES : null, false, false);
        }
        $this->productRepository->save($product);
        return count($files);
    }
}
