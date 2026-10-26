<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Test\Unit\Model\Step;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Link as LinkResource;
use PHPUnit\Framework\TestCase;
use TechNest\DataSeeder\Model\Step\Related;

class RelatedTest extends TestCase
{
    public function testLinksExcludeSelfAndSortByClosestPrice(): void
    {
        $step = new Related(
            $this->createStub(ProductCollectionFactory::class),
            $this->createStub(CategoryCollectionFactory::class),
            $this->createStub(Visibility::class),
            $this->createStub(LinkResource::class)
        );
        $prices = [1 => 1000.0, 2 => 400.0, 3 => 1100.0, 4 => 950.0, 5 => 2000.0];

        $links = $step->buildLinks(1, [1, 2, 3, 4, 5], $prices);

        self::assertSame([4, 3, 2, 5], array_keys($links));
        self::assertSame(['position' => 1], $links[4]);
        self::assertArrayNotHasKey(1, $links);
    }

    public function testLinksAreCappedAtEight(): void
    {
        $step = new Related(
            $this->createStub(ProductCollectionFactory::class),
            $this->createStub(CategoryCollectionFactory::class),
            $this->createStub(Visibility::class),
            $this->createStub(LinkResource::class)
        );
        $ids = range(1, 12);

        self::assertCount(8, $step->buildLinks(1, $ids, array_fill_keys($ids, 10.0)));
    }
}
