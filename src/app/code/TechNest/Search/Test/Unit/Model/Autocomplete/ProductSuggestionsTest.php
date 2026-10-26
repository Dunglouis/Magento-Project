<?php
declare(strict_types=1);

namespace TechNest\Search\Test\Unit\Model\Autocomplete;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Search\Api\SearchInterface;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TechNest\Search\Model\Autocomplete\ProductSuggestions;

class ProductSuggestionsTest extends TestCase
{
    /**
     * @var ProductSuggestions
     */
    private ProductSuggestions $provider;

    protected function setUp(): void
    {
        $this->provider = new ProductSuggestions(
            $this->createStub(QueryFactory::class),
            $this->createStub(SearchInterface::class),
            $this->createStub(SearchCriteriaBuilder::class),
            $this->createStub(FilterBuilder::class),
            $this->createStub(SortOrderBuilder::class),
            $this->createStub(ProductCollectionFactory::class),
            $this->createStub(Visibility::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(ImageHelper::class),
            $this->createStub(PriceCurrencyInterface::class),
            $this->createStub(ItemFactory::class)
        );
    }

    /**
     * @return array<string,array{string,string[]}>
     */
    public static function texts(): array
    {
        return [
            'single word' => ['sam', ['sam']],
            'trimmed, lower case' => ['  Legion T5 ', ['legion', 't5']],
            'repeated spaces and words' => ['rog   ROG strix', ['rog', 'strix']],
            'vietnamese' => ['Tai Nghe', ['tai', 'nghe']],
            'empty' => ['   ', []],
        ];
    }

    /**
     * @param string $text
     * @param string[] $expected
     */
    #[DataProvider('texts')]
    public function testSearchWords(string $text, array $expected): void
    {
        $this->assertSame($expected, $this->provider->searchWords($text));
    }

    public function testSearchEngineResultsComeFirstWithoutDuplicates(): void
    {
        $this->assertSame([5, 2, 9, 7], $this->provider->mergeIds([5, 2], [2, 9, 5, 7], 6));
    }

    public function testMergedIdsAreCappedAtTheLimit(): void
    {
        $this->assertSame([1, 2, 3], $this->provider->mergeIds([1, 2], [3, 4, 5], 3));
    }

    public function testLikePatternTakesWildcardsLiterally(): void
    {
        $this->assertSame('%100\%\_off%', $this->provider->likePattern('100%_off'));
    }
}
