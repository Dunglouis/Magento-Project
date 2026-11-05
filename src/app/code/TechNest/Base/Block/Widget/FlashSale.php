<?php
declare(strict_types=1);

namespace TechNest\Base\Block\Widget;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogWidget\Block\Product\ProductsList;
use Magento\CatalogWidget\Model\Rule;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Rule\Model\Condition\Sql\Builder as SqlBuilder;
use Magento\Widget\Helper\Conditions;
use TechNest\Base\Model\SaleInfo;

/**
 * Home page "Flash sale" shelf: visible products whose special price period runs today, with a countdown
 * to the earliest end. Used through {{widget type="TechNest\Base\Block\Widget\FlashSale" ...}}.
 */
class FlashSale extends ProductsList
{
    /**
     * @param Context $context
     * @param CollectionFactory $productCollectionFactory
     * @param Visibility $catalogProductVisibility
     * @param HttpContext $httpContext
     * @param SqlBuilder $sqlBuilder
     * @param Rule $rule
     * @param Conditions $conditionsHelper
     * @param SaleInfo $saleInfo
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        Visibility $catalogProductVisibility,
        HttpContext $httpContext,
        SqlBuilder $sqlBuilder,
        Rule $rule,
        Conditions $conditionsHelper,
        private readonly SaleInfo $saleInfo,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $productCollectionFactory,
            $catalogProductVisibility,
            $httpContext,
            $sqlBuilder,
            $rule,
            $conditionsHelper,
            $data
        );
    }

    /**
     * No product conditions in the widget directive: start from an empty "all of" rule
     *
     * @return \Magento\Rule\Model\Condition\Combine
     */
    protected function getConditions()
    {
        if (!$this->getData('conditions_encoded') && !$this->getData('conditions')) {
            $this->setData('conditions_encoded', $this->conditionsHelper->encode([
                '1' => [
                    'type' => \Magento\CatalogWidget\Model\Rule\Condition\Combine::class,
                    'aggregator' => 'all',
                    'value' => '1',
                    'new_child' => '',
                ],
            ]));
        }
        return parent::getConditions();
    }

    /**
     * Products whose special price period contains today
     *
     * Filtered here rather than in createCollection(): Magento_ConfigurableProduct's widget plugin rebuilds
     * the list from this base collection to add parents of matching variants.
     *
     * @return Collection
     */
    public function getBaseCollection(): Collection
    {
        $today = $this->saleInfo->today();
        return parent::getBaseCollection()
            ->addAttributeToFilter('special_to_date', ['gteq' => $today])
            ->addAttributeToFilter(
                [
                    ['attribute' => 'special_from_date', 'null' => true],
                    ['attribute' => 'special_from_date', 'lteq' => $today . ' 23:59:59'],
                ],
                null,
                'left'
            );
    }

    /**
     * Earliest end (unix time) among the listed products, null when none of them is on sale
     *
     * @return int|null
     */
    public function getEndsAt(): ?int
    {
        $ends = [];
        foreach ($this->getProductCollection() ?? [] as $product) {
            /** @var Product $product */
            $endsAt = $this->saleInfo->getEndsAt($product);
            if ($endsAt !== null && $this->saleInfo->isOnSale($product)) {
                $ends[] = $endsAt;
            }
        }
        return $ends ? min($ends) : null;
    }

    /**
     * The list changes when the day changes
     *
     * @return array<int,mixed>
     */
    public function getCacheKeyInfo()
    {
        return array_merge(parent::getCacheKeyInfo(), ['TECHNEST_FLASH_SALE', $this->saleInfo->today()]);
    }
}
