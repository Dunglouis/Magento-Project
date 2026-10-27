<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\CatalogRule\Model\Indexer\Rule\RuleProductProcessor;
use Magento\CatalogRule\Model\ResourceModel\Rule as CatalogRuleResource;
use Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory as CatalogRuleCollectionFactory;
use Magento\CatalogRule\Model\Rule\Condition\Combine as CatalogCombine;
use Magento\CatalogRule\Model\Rule\Condition\Product as CatalogProductCondition;
use Magento\CatalogRule\Model\RuleFactory as CatalogRuleFactory;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\OfflineShipping\Model\SalesRule\Rule as FreeShippingRule;
use Magento\SalesRule\Model\ResourceModel\Rule as CartRuleResource;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as CartRuleCollectionFactory;
use Magento\SalesRule\Model\Rule as CartRule;
use Magento\SalesRule\Model\RuleFactory as CartRuleFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\DataSeeder\Model\CategoryResolver;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * Catalog price rules (discount by category) and coupon cart rules from data/promotions.json.
 * Rules are matched by name, so re-runs update them instead of adding copies.
 */
class Promotions implements StepInterface
{
    /**
     * @param SeedData $seedData
     * @param CategoryResolver $categoryResolver
     * @param StoreManagerInterface $storeManager
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param CatalogRuleFactory $catalogRuleFactory
     * @param CatalogRuleResource $catalogRuleResource
     * @param CatalogRuleCollectionFactory $catalogRuleCollectionFactory
     * @param RuleProductProcessor $ruleProductProcessor
     * @param CartRuleFactory $cartRuleFactory
     * @param CartRuleResource $cartRuleResource
     * @param CartRuleCollectionFactory $cartRuleCollectionFactory
     */
    public function __construct(
        private readonly SeedData $seedData,
        private readonly CategoryResolver $categoryResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly GroupCollectionFactory $groupCollectionFactory,
        private readonly CatalogRuleFactory $catalogRuleFactory,
        private readonly CatalogRuleResource $catalogRuleResource,
        private readonly CatalogRuleCollectionFactory $catalogRuleCollectionFactory,
        private readonly RuleProductProcessor $ruleProductProcessor,
        private readonly CartRuleFactory $cartRuleFactory,
        private readonly CartRuleResource $cartRuleResource,
        private readonly CartRuleCollectionFactory $cartRuleCollectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Catalog price rules and coupon codes';
    }

    /**
     * @inheritdoc
     */
    public function execute(OutputInterface $output): void
    {
        $data = $this->seedData->getPromotions();
        $websiteIds = array_map(
            static fn ($website): int => (int)$website->getId(),
            array_values($this->storeManager->getWebsites())
        );
        $groupIds = array_map('intval', $this->groupCollectionFactory->create()->getAllIds());
        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeIds[$store->getCode()] = (int)$store->getId();
        }

        $catalogRuleIds = [];
        foreach ($data['catalog_rules'] ?? [] as $entry) {
            $categoryIds = array_map(
                fn (string $path): int => $this->categoryResolver->getId($path),
                $this->subtreePaths($entry['category'], $this->seedData->getCategoryUrlPaths())
            );
            $catalogRuleIds[] = $this->saveCatalogRule($entry, $categoryIds, $websiteIds, $groupIds);
            $output->writeln(sprintf('  catalog rule "%s": %d categories', $entry['name'], count($categoryIds)));
        }
        if ($catalogRuleIds) {
            // A partial reindex only revisits products already in the rule index, so new rules need a full one
            $this->ruleProductProcessor->markIndexerAsInvalid();
        }

        foreach ($data['cart_rules'] ?? [] as $entry) {
            $rule = $this->loadByName(
                $this->cartRuleCollectionFactory->create(),
                $this->cartRuleFactory->create(),
                $entry['name']
            );
            $rule->addData($this->cartRuleData($entry, $websiteIds, $groupIds, $storeIds));
            $this->cartRuleResource->save($rule);
            $output->writeln(sprintf('  coupon %s: "%s"', $entry['coupon'], $entry['name']));
        }
    }

    /**
     * The category URL path and all of its descendants (a catalog rule only matches direct assignments)
     *
     * @param string $root
     * @param string[] $paths
     * @return string[]
     */
    public function subtreePaths(string $root, array $paths): array
    {
        return array_values(array_filter(
            $paths,
            static fn (string $path): bool => $path === $root || str_starts_with($path, $root . '/')
        ));
    }

    /**
     * Admin-form style conditions: product is in one of the given categories
     *
     * @param int[] $categoryIds
     * @return array<int|string,array<string,mixed>>
     */
    public function catalogConditions(array $categoryIds): array
    {
        return [
            '1' => ['type' => CatalogCombine::class, 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
            '1--1' => [
                'type' => CatalogProductCondition::class,
                'attribute' => 'category_ids',
                'operator' => '()',
                'value' => implode(',', $categoryIds),
            ],
        ];
    }

    /**
     * Cart rule fields for a coupon entry: percent off the cart and/or free shipping on every item
     *
     * Free shipping is set per item (not per shipment) so every carrier, Flat Rate included, drops to zero.
     *
     * @param array<string,mixed> $entry
     * @param int[] $websiteIds
     * @param int[] $groupIds
     * @param array<string,int> $storeIds store code => store id
     * @return array<string,mixed>
     */
    public function cartRuleData(array $entry, array $websiteIds, array $groupIds, array $storeIds): array
    {
        $labels = [Store::DEFAULT_STORE_ID => $entry['label']['en']];
        foreach ($entry['label'] as $code => $label) {
            if (isset($storeIds[$code])) {
                $labels[$storeIds[$code]] = $label;
            }
        }
        return [
            'name' => $entry['name'],
            'is_active' => 1,
            'website_ids' => $websiteIds,
            'customer_group_ids' => $groupIds,
            'coupon_type' => CartRule::COUPON_TYPE_SPECIFIC,
            'coupon_code' => $entry['coupon'],
            'uses_per_coupon' => 0,
            'uses_per_customer' => (int)$entry['uses_per_customer'],
            'simple_action' => CartRule::BY_PERCENT_ACTION,
            'discount_amount' => (float)$entry['discount'],
            'discount_qty' => 0,
            'apply_to_shipping' => 0,
            'stop_rules_processing' => 0,
            'simple_free_shipping' => $entry['free_shipping'] ? FreeShippingRule::FREE_SHIPPING_ITEM : 0,
            'sort_order' => (int)$entry['sort_order'],
            'store_labels' => $labels,
        ];
    }

    /**
     * Create or update a catalog rule
     *
     * @param array<string,mixed> $entry
     * @param int[] $categoryIds
     * @param int[] $websiteIds
     * @param int[] $groupIds
     * @return int rule id
     */
    private function saveCatalogRule(array $entry, array $categoryIds, array $websiteIds, array $groupIds): int
    {
        $rule = $this->loadByName(
            $this->catalogRuleCollectionFactory->create(),
            $this->catalogRuleFactory->create(),
            $entry['name']
        );
        $rule->addData([
            'name' => $entry['name'],
            'description' => $entry['description'],
            'is_active' => 1,
            'website_ids' => $websiteIds,
            'customer_group_ids' => $groupIds,
            'simple_action' => 'by_percent',
            'discount_amount' => (float)$entry['discount'],
            'stop_rules_processing' => 0,
            'sort_order' => (int)$entry['sort_order'],
        ]);
        $rule->loadPost(['conditions' => $this->catalogConditions($categoryIds)]);
        $this->catalogRuleResource->save($rule);
        return (int)$rule->getId();
    }

    /**
     * The existing rule with that name, or the given new one
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection $collection
     * @param \Magento\Rule\Model\AbstractModel $newRule
     * @param string $name
     * @return \Magento\Rule\Model\AbstractModel
     */
    private function loadByName($collection, $newRule, string $name)
    {
        /** @var \Magento\Rule\Model\AbstractModel $existing */
        $existing = $collection->addFieldToFilter('name', $name)->setPageSize(1)->getFirstItem();
        return $existing->getId() ? $existing : $newRule;
    }
}
