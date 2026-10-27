<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Test\Unit\Model\Step;

use Magento\CatalogRule\Model\Indexer\Rule\RuleProductProcessor;
use Magento\CatalogRule\Model\ResourceModel\Rule as CatalogRuleResource;
use Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory as CatalogRuleCollectionFactory;
use Magento\CatalogRule\Model\RuleFactory as CatalogRuleFactory;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\SalesRule\Model\ResourceModel\Rule as CartRuleResource;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as CartRuleCollectionFactory;
use Magento\SalesRule\Model\Rule as CartRule;
use Magento\SalesRule\Model\RuleFactory as CartRuleFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use TechNest\DataSeeder\Model\CategoryResolver;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\Step\Promotions;

class PromotionsTest extends TestCase
{
    /**
     * @var Promotions
     */
    private Promotions $step;

    protected function setUp(): void
    {
        $this->step = new Promotions(
            $this->createStub(SeedData::class),
            $this->createStub(CategoryResolver::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(GroupCollectionFactory::class),
            $this->createStub(CatalogRuleFactory::class),
            $this->createStub(CatalogRuleResource::class),
            $this->createStub(CatalogRuleCollectionFactory::class),
            $this->createStub(RuleProductProcessor::class),
            $this->createStub(CartRuleFactory::class),
            $this->createStub(CartRuleResource::class),
            $this->createStub(CartRuleCollectionFactory::class)
        );
    }

    /**
     * @param bool $freeShipping
     * @param float $discount
     * @return array<string,mixed>
     */
    private static function coupon(bool $freeShipping, float $discount): array
    {
        return ['name' => 'R', 'coupon' => 'CODE', 'discount' => $discount, 'free_shipping' => $freeShipping,
            'uses_per_customer' => 1, 'sort_order' => 5, 'label' => ['en' => 'Label', 'vi' => 'Nhãn']];
    }

    public function testSubtreeKeepsTheRootAndItsChildrenOnly(): void
    {
        $paths = ['accessories', 'accessories/mice', 'accessories-sale', 'laptops/gaming', 'accessories/keyboards'];

        self::assertSame(
            ['accessories', 'accessories/mice', 'accessories/keyboards'],
            $this->step->subtreePaths('accessories', $paths)
        );
    }

    public function testCatalogConditionMatchesAnyOfTheCategories(): void
    {
        $conditions = $this->step->catalogConditions([66, 67, 68]);

        self::assertSame('category_ids', $conditions['1--1']['attribute']);
        self::assertSame('()', $conditions['1--1']['operator']);
        self::assertSame('66,67,68', $conditions['1--1']['value']);
        self::assertSame('all', $conditions['1']['aggregator']);
    }

    public function testPercentCouponHasNoFreeShipping(): void
    {
        $data = $this->step->cartRuleData(self::coupon(false, 10), [1], [0, 1], ['en' => 1, 'vi' => 2]);

        self::assertSame(CartRule::COUPON_TYPE_SPECIFIC, $data['coupon_type']);
        self::assertSame('CODE', $data['coupon_code']);
        self::assertSame(CartRule::BY_PERCENT_ACTION, $data['simple_action']);
        self::assertSame(10.0, $data['discount_amount']);
        self::assertSame(0, $data['simple_free_shipping']);
        self::assertSame(1, $data['uses_per_customer']);
    }

    public function testFreeShippingCouponAppliesToEveryItem(): void
    {
        $data = $this->step->cartRuleData(self::coupon(true, 0), [1], [0, 1], ['en' => 1, 'vi' => 2]);

        self::assertSame(1, $data['simple_free_shipping']);
        self::assertSame(0.0, $data['discount_amount']);
    }

    public function testLabelsGoToDefaultAndKnownStoreViews(): void
    {
        $entry = self::coupon(false, 10);
        $entry['label']['fr'] = 'Étiquette';

        $data = $this->step->cartRuleData($entry, [1], [0], ['en' => 1, 'vi' => 2]);

        self::assertSame([0 => 'Label', 1 => 'Label', 2 => 'Nhãn'], $data['store_labels']);
    }
}
