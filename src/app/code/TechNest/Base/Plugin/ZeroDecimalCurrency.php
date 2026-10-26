<?php
declare(strict_types=1);

namespace TechNest\Base\Plugin;

use Magento\Directory\Model\Currency;
use Magento\Framework\Locale\Format;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Currencies without minor units (VND) are shown without decimals: "21.475.000 ₫", not "21.475.000,00 ₫".
 * Covers server-side formatting (Currency::formatTxt) and the JS price format (Format::getPriceFormat).
 */
class ZeroDecimalCurrency
{
    public const CURRENCIES = ['VND'];

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Server-side: force precision 0
     *
     * @param Currency $subject
     * @param mixed $price
     * @param array<string,mixed> $options
     * @return array{mixed, array<string,mixed>}
     */
    public function beforeFormatTxt(Currency $subject, $price, $options = []): array
    {
        if (in_array((string)$subject->getCode(), self::CURRENCIES, true)) {
            $options['precision'] = 0;
        }
        return [$price, $options];
    }

    /**
     * JS price format (price boxes, cart, checkout): no decimals
     *
     * @param Format $subject
     * @param array<string,mixed> $result
     * @param string|null $localeCode
     * @param string|null $currencyCode
     * @return array<string,mixed>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetPriceFormat(Format $subject, array $result, $localeCode = null, $currencyCode = null): array
    {
        $store = $this->storeManager->getStore();
        $code = $currencyCode ?: ($store instanceof Store ? (string)$store->getCurrentCurrencyCode() : '');
        if (in_array($code, self::CURRENCIES, true)) {
            $result['precision'] = 0;
            $result['requiredPrecision'] = 0;
        }
        return $result;
    }
}
