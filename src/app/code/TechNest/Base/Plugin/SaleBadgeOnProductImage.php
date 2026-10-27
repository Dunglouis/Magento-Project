<?php
declare(strict_types=1);

namespace TechNest\Base\Plugin;

use Magento\Catalog\Block\Product\Image as ImageBlock;
use Magento\Catalog\Block\Product\ImageFactory;
use Magento\Catalog\Model\Product;
use TechNest\Base\Model\SaleInfo;

/**
 * Marks product card images (category grid/list, home shelves, related/up-sell) of products on sale,
 * so the theme's image template can draw the "Sale" badge and the flash sale countdown.
 */
class SaleBadgeOnProductImage
{
    private const CARD_IMAGE_IDS = [
        'category_page_grid',
        'category_page_list',
        'new_products_content_widget_grid',
        'related_products_list',
        'upsell_products_list',
    ];

    /**
     * @param SaleInfo $saleInfo
     */
    public function __construct(
        private readonly SaleInfo $saleInfo
    ) {
    }

    /**
     * Add tn_sale (bool) and tn_sale_ends_at (unix time or null) to card images
     *
     * @param ImageFactory $subject
     * @param ImageBlock $result
     * @param Product $product
     * @param string $imageId
     * @return ImageBlock
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCreate(
        ImageFactory $subject,
        ImageBlock $result,
        Product $product,
        string $imageId
    ): ImageBlock {
        if (in_array($imageId, self::CARD_IMAGE_IDS, true) && $this->saleInfo->isOnSale($product)) {
            $result->setData('tn_sale', true);
            $result->setData('tn_sale_ends_at', $this->saleInfo->getEndsAt($product));
        }
        return $result;
    }
}
