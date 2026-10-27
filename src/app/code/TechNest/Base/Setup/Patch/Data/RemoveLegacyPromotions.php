<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\CatalogRule\Model\Indexer\Rule\RuleProductProcessor;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Deletes the price rules typed in by hand during the Magento lessons (their coupons go with them);
 * the store's own promotions come from `technest:seed:run --step=promotions`.
 */
class RemoveLegacyPromotions implements DataPatchInterface
{
    private const CATALOG_RULES = [
        'Giảm 10% ngày quốc tế phụ nữ',
        'Khuyến mãi sản phẩm 10/10 mega live',
    ];

    private const CART_RULES = [
        'Khuyến mãi thành viên',
        'Siêu khuyến mãi tháng 10',
    ];

    /**
     * @param ResourceConnection $resource
     * @param RuleProductProcessor $ruleProductProcessor
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly RuleProductProcessor $ruleProductProcessor
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $connection = $this->resource->getConnection();
        $catalogRuleTable = $this->resource->getTableName('catalogrule');
        $catalogRuleIds = $connection->fetchCol(
            $connection->select()->from($catalogRuleTable, 'rule_id')->where('name IN (?)', self::CATALOG_RULES)
        );
        // Foreign keys cascade to websites, customer groups, labels and coupons
        $connection->delete(
            $this->resource->getTableName('salesrule'),
            ['name IN (?)' => self::CART_RULES]
        );
        if ($catalogRuleIds) {
            $connection->delete($catalogRuleTable, ['rule_id IN (?)' => $catalogRuleIds]);
            // The rule product index has no foreign key; indexed rule prices are rebuilt on the next reindex
            $connection->delete(
                $this->resource->getTableName('catalogrule_product'),
                ['rule_id IN (?)' => $catalogRuleIds]
            );
            $this->ruleProductProcessor->markIndexerAsInvalid();
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
