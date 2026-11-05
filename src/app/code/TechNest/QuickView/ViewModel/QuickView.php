<?php
declare(strict_types=1);

namespace TechNest\QuickView\ViewModel;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Checkout\Helper\Cart;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Filter\FilterManager;
use Magento\Framework\Pricing\Render;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
use TechNest\ProductSpecs\ViewModel\Specs;
use TechNest\QuickView\Model\Purchase;

/**
 * Content of the quick view modal: photo, price, summary, key specs and the buy button
 */
class QuickView implements ArgumentInterface
{
    private const SUMMARY_LENGTH = 220;
    private const SPEC_ROWS = 5;

    /**
     * @param Image $imageHelper
     * @param Cart $cartHelper
     * @param FormKey $formKey
     * @param FilterManager $filterManager
     * @param LayoutInterface $layout
     * @param Specs $specs
     * @param Purchase $purchase
     */
    public function __construct(
        private readonly Image $imageHelper,
        private readonly Cart $cartHelper,
        private readonly FormKey $formKey,
        private readonly FilterManager $filterManager,
        private readonly LayoutInterface $layout,
        private readonly Specs $specs,
        private readonly Purchase $purchase
    ) {
    }

    /**
     * Product photo URL
     *
     * @param Product $product
     * @return string
     */
    public function getImageUrl(Product $product): string
    {
        return (string)$this->imageHelper->init($product, 'product_page_image_medium')->getUrl();
    }

    /**
     * Price box as on the listing (special price, "As low as"...)
     *
     * @param Product $product
     * @return string
     */
    public function getPriceHtml(Product $product): string
    {
        $render = $this->layout->getBlock('product.price.render.default');
        if (!$render instanceof Render) {
            /** @var Render $render */
            $render = $this->layout->createBlock(
                Render::class,
                'product.price.render.default',
                ['data' => ['price_render_handle' => 'catalog_product_prices']]
            );
        }
        return (string)$render->render(FinalPrice::PRICE_CODE, $product, ['zone' => Render::ZONE_ITEM_LIST]);
    }

    /**
     * Short description as plain text
     *
     * @param Product $product
     * @return string
     */
    public function getSummary(Product $product): string
    {
        $text = (string)($product->getData('short_description') ?: $product->getData('description'));
        $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags($text)));
        return (string)$this->filterManager->truncate($text, ['length' => self::SUMMARY_LENGTH, 'etc' => '…']);
    }

    /**
     * First rows of the specification sheet
     *
     * @param Product $product
     * @return array<int, array<string, mixed>> Rows with label and value
     */
    public function getKeySpecs(Product $product): array
    {
        $rows = [];
        foreach ($this->specs->getGroups($product) as $group) {
            foreach ((array)$group['rows'] as $row) {
                $rows[] = $row;
            }
        }
        return array_slice($rows, 0, self::SPEC_ROWS);
    }

    /**
     * Buy from the modal, or choose options on the product page
     *
     * @param Product $product
     * @return bool
     */
    public function canAddDirectly(Product $product): bool
    {
        return $this->purchase->canAddDirectly(
            (string)$product->getTypeId(),
            (bool)$product->isSalable(),
            (bool)$product->getRequiredOptions()
        );
    }

    /**
     * Add to cart URL
     *
     * @param Product $product
     * @return string
     */
    public function getAddToCartUrl(Product $product): string
    {
        return $this->cartHelper->getAddUrl($product);
    }

    /**
     * Form key for the add to cart form
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
