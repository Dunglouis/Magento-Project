<?php
declare(strict_types=1);

namespace TechNest\Installment\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Locale\Format;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use TechNest\Installment\Model\Calculator;
use TechNest\Installment\Model\Config;

/**
 * Instalment plans of the product page, in the shopper's display currency
 */
class Installment implements ArgumentInterface
{
    /**
     * Bundle and grouped products have no single price to split
     */
    private const TYPES = ['simple', 'virtual', 'configurable'];

    /**
     * @param Config $config
     * @param Calculator $calculator
     * @param PriceCurrencyInterface $priceCurrency
     * @param Format $localeFormat
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Config $config,
        private readonly Calculator $calculator,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Format $localeFormat,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Plans can apply to this product (the price may still be under the minimum)
     *
     * @param Product $product
     * @return bool
     */
    public function isOffered(Product $product): bool
    {
        return $this->config->isEnabled() && in_array($product->getTypeId(), self::TYPES, true);
    }

    /**
     * Terms with their interest
     *
     * @return float[] Months => % per month
     */
    public function getTerms(): array
    {
        return $this->config->getRates();
    }

    /**
     * Plans for the product's current final price, keyed by months; empty under the minimum price
     *
     * @param Product $product
     * @return array<int, array{months: int, rate: float, monthly: float, total: float, interest: float}>
     */
    public function getPlans(Product $product): array
    {
        if (!$this->isOffered($product)) {
            return [];
        }
        $price = (float)$product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();
        if ($price < $this->getMinPrice()) {
            return [];
        }
        $plans = $this->calculator->calculate($price, $this->config->getRates(), $this->getPrecision());
        return array_column($plans, null, 'months');
    }

    /**
     * Lowest monthly payment of the plans
     *
     * @param mixed[][] $plans Plans from getPlans()
     * @return float|null
     */
    public function getLowestMonthly(array $plans): ?float
    {
        return $this->calculator->lowestMonthly($plans);
    }

    /**
     * Amount already in the display currency, formatted
     *
     * @param float $amount
     * @return string
     */
    public function formatPrice(float $amount): string
    {
        return $this->priceCurrency->format($amount, false, $this->getPrecision());
    }

    /**
     * Settings for js/installment, which recalculates when the price box changes
     *
     * @param Product $product
     * @return array<string, mixed>
     */
    public function getJsConfig(Product $product): array
    {
        return [
            'productId' => (int)$product->getId(),
            'rates' => $this->config->getRates(),
            'minPrice' => $this->getMinPrice(),
            'priceFormat' => $this->getPriceFormat(),
        ];
    }

    /**
     * Minimum price in the display currency
     *
     * @return float
     */
    private function getMinPrice(): float
    {
        return (float)$this->priceCurrency->convert($this->config->getMinPrice());
    }

    /**
     * Decimals of the display currency
     *
     * @return int
     */
    private function getPrecision(): int
    {
        return (int)($this->getPriceFormat()['precision'] ?? 2);
    }

    /**
     * JS price format of the display currency
     *
     * @return array<string, mixed>
     */
    private function getPriceFormat(): array
    {
        $store = $this->storeManager->getStore();
        $currency = $store instanceof Store ? (string)$store->getCurrentCurrencyCode() : null;
        return $this->localeFormat->getPriceFormat(null, $currency);
    }
}
