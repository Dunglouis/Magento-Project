<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Test\Unit\Model\Cms;

use Magento\Framework\Data\Wysiwyg\Normalizer;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Widget\Helper\Conditions;
use PHPUnit\Framework\TestCase;
use TechNest\DataSeeder\Model\Cms\TemplateRenderer;

class TemplateRendererTest extends TestCase
{
    /**
     * @var Conditions
     */
    private Conditions $conditions;

    /**
     * @var TemplateRenderer
     */
    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->conditions = new Conditions(new Json(), new Normalizer());
        $this->renderer = new TemplateRenderer($this->conditions);
    }

    public function testImagePlaceholderBecomesMediaDirectiveOrEmpty(): void
    {
        $images = ['TN-A' => '/a/b/a.jpg'];
        $html = $this->renderer->render(
            '<img src="%image:TN-A%"><img src="%image:TN-MISSING%">',
            static fn (string $sku): ?string => $images[$sku] ?? null
        );

        self::assertSame('<img src="{{media url="catalog/product/a/b/a.jpg"}}"><img src="">', $html);
    }

    public function testFlashSalePlaceholderBecomesFlashSaleWidget(): void
    {
        $html = $this->renderer->render('<div>%flashsale%</div>', static fn (): ?string => null);

        self::assertSame('<div>' . TemplateRenderer::FLASH_SALE_WIDGET . '</div>', $html);
        self::assertStringContainsString('type="TechNest\\Base\\Block\\Widget\\FlashSale"', $html);
    }

    public function testProductsPlaceholderBecomesSkuFilteredWidget(): void
    {
        $html = $this->renderer->render('<div>%products:TN-A,TN-B%</div>', static fn (): ?string => null);

        self::assertMatchesRegularExpression(
            '/^<div>\{\{widget type="Magento\\\\CatalogWidget\\\\Block\\\\Product\\\\ProductsList" .*'
            . 'products_count="2" .*conditions_encoded="([^"]+)"\}\}<\/div>$/',
            $html
        );
        preg_match('/conditions_encoded="([^"]+)"/', $html, $match);
        $conditions = $this->conditions->decode($match[1]);
        self::assertSame('sku', $conditions['1--1']['attribute']);
        self::assertSame('()', $conditions['1--1']['operator']);
        self::assertSame('TN-A, TN-B', $conditions['1--1']['value']);
    }

    public function testShelfPlaceholderUsesShelfTemplate(): void
    {
        $html = $this->renderer->render('%shelf:TN-A%', static fn (): ?string => null);

        self::assertStringContainsString('template="' . TemplateRenderer::TEMPLATES['shelf'] . '"', $html);
        self::assertStringContainsString('products_count="1"', $html);
    }
}
