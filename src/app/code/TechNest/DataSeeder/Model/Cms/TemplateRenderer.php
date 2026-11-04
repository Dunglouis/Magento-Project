<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Cms;

use Magento\Widget\Helper\Conditions;

/**
 * Turns seed CMS templates into Magento CMS content.
 *
 * Placeholders:
 *  - %image:SKU%          -> {{media url="catalog/product/..."}} of the product's base image
 *  - %products:SKU1,SKU2% -> Catalog Products List widget (grid) limited to those SKUs
 *  - %shelf:SKU1,SKU2%    -> same widget with the theme's horizontal "shelf" template
 *  - %flashsale%          -> TechNest_Base flash sale shelf (products whose special price runs today)
 */
class TemplateRenderer
{
    public const TEMPLATES = [
        'products' => 'Magento_CatalogWidget::product/widget/content/grid.phtml',
        'shelf' => 'Magento_CatalogWidget::product/widget/content/shelf.phtml',
    ];

    public const FLASH_SALE_WIDGET = '{{widget type="TechNest\\Base\\Block\\Widget\\FlashSale" show_pager="0"'
        . ' products_count="12" template="TechNest_Base::widget/flash-sale.phtml"}}';

    /**
     * @param Conditions $conditionsHelper
     */
    public function __construct(
        private readonly Conditions $conditionsHelper
    ) {
    }

    /**
     * Replace placeholders; $imageResolver maps a SKU to its catalog image path ("/a/b/file.jpg") or null
     *
     * @param string $template
     * @param callable $imageResolver fn(string $sku): ?string
     * @return string
     */
    public function render(string $template, callable $imageResolver): string
    {
        $content = (string)preg_replace_callback(
            '/%image:([A-Za-z0-9_-]+)%/',
            static function (array $match) use ($imageResolver): string {
                $path = $imageResolver($match[1]);
                return $path ? '{{media url="catalog/product' . $path . '"}}' : '';
            },
            $template
        );

        $content = str_replace('%flashsale%', self::FLASH_SALE_WIDGET, $content);

        return (string)preg_replace_callback(
            '/%(products|shelf):([A-Za-z0-9_,-]+)%/',
            fn (array $match): string => $this->productsWidget(
                array_filter(explode(',', $match[2])),
                self::TEMPLATES[$match[1]]
            ),
            $content
        );
    }

    /**
     * Build a Catalog Products List widget directive for the given SKUs
     *
     * @param string[] $skus
     * @param string $template
     * @return string
     */
    public function productsWidget(array $skus, string $template = self::TEMPLATES['products']): string
    {
        $conditions = $this->conditionsHelper->encode([
            '1' => [
                'type' => \Magento\CatalogWidget\Model\Rule\Condition\Combine::class,
                'aggregator' => 'all',
                'value' => '1',
                'new_child' => '',
            ],
            '1--1' => [
                'type' => \Magento\CatalogWidget\Model\Rule\Condition\Product::class,
                'attribute' => 'sku',
                'operator' => '()',
                'value' => implode(', ', $skus),
            ],
        ]);

        return sprintf(
            '{{widget type="Magento\\CatalogWidget\\Block\\Product\\ProductsList" show_pager="0"'
            . ' products_count="%d" template="%s" conditions_encoded="%s"}}',
            count($skus),
            $template,
            $conditions
        );
    }
}
