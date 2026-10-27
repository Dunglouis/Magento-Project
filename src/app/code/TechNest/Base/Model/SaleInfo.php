<?php
declare(strict_types=1);

namespace TechNest\Base\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Pricing\Price\ConfigurableOptionsProviderInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Sale state of a product for the "Sale" badge and the flash sale countdown.
 *
 * A product is on sale when its final price (special price or catalog price rule) is below the regular price;
 * a configurable product when any of its variants is. The flash sale ends at 23:59:59 store time of
 * special_to_date, which the seeder also sets on configurable parents.
 */
class SaleInfo
{
    private const PRICED_TYPES = [ProductType::TYPE_SIMPLE, ProductType::TYPE_VIRTUAL, Configurable::TYPE_CODE];

    /**
     * @param ConfigurableOptionsProviderInterface $configurableOptionsProvider
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly ConfigurableOptionsProviderInterface $configurableOptionsProvider,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Whether the product (or one of its variants) sells below its regular price
     *
     * @param Product $product
     * @return bool
     */
    public function isOnSale(Product $product): bool
    {
        if (!in_array($product->getTypeId(), self::PRICED_TYPES, true)) {
            return false;
        }
        $items = $product->getTypeId() === Configurable::TYPE_CODE
            ? $this->configurableOptionsProvider->getProducts($product)
            : [$product];
        foreach ($items as $item) {
            /** @var Product $item */
            $priceInfo = $item->getPriceInfo();
            $final = (float)$priceInfo->getPrice(FinalPrice::PRICE_CODE)->getValue();
            $regular = (float)$priceInfo->getPrice(RegularPrice::PRICE_CODE)->getValue();
            if ($final < $regular) {
                return true;
            }
        }
        return false;
    }

    /**
     * Unix time the product's special price ends, or null when it has no running special price period
     *
     * @param Product $product
     * @return int|null
     */
    public function getEndsAt(Product $product): ?int
    {
        $from = $product->getData('special_from_date');
        $to = $product->getData('special_to_date');
        if (!$to || !$this->isRunning($from ? (string)$from : null, (string)$to, $this->today())) {
            return null;
        }
        return $this->endOfDay((string)$to, $this->timezone->getConfigTimezone());
    }

    /**
     * Today's date in the store time zone (Y-m-d)
     *
     * @return string
     */
    public function today(): string
    {
        return $this->timezone->date()->format('Y-m-d');
    }

    /**
     * Whether a [from, to] date period (inclusive, time of day ignored) contains the given day
     *
     * @param string|null $from
     * @param string $to
     * @param string $today Y-m-d
     * @return bool
     */
    public function isRunning(?string $from, string $to, string $today): bool
    {
        return ($from === null || substr($from, 0, 10) <= $today) && substr($to, 0, 10) >= $today;
    }

    /**
     * Unix time of 23:59:59 on the given date in the given time zone
     *
     * @param string $date Y-m-d, optionally followed by a time that is ignored
     * @param string $timezone
     * @return int
     */
    public function endOfDay(string $date, string $timezone): int
    {
        return (new \DateTimeImmutable(substr($date, 0, 10) . ' 23:59:59', new \DateTimeZone($timezone)))
            ->getTimestamp();
    }
}
