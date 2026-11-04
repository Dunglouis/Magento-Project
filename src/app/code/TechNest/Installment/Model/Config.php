<?php
declare(strict_types=1);

namespace TechNest\Installment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Stores > Configuration > TechNest > Installment
 */
class Config
{
    private const PREFIX = 'technest_installment/general/';
    public const TERMS = [3, 6, 12];

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Show plans on the product page
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'enabled', ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Minimum product price in the base currency
     *
     * @return float
     */
    public function getMinPrice(): float
    {
        return (float)$this->scopeConfig->getValue(self::PREFIX . 'min_price', ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Interest per term
     *
     * @return float[] Months => % per month
     */
    public function getRates(): array
    {
        $rates = [];
        foreach (self::TERMS as $months) {
            $rates[$months] = (float)$this->scopeConfig->getValue(
                self::PREFIX . 'rate_' . $months,
                ScopeInterface::SCOPE_WEBSITE
            );
        }
        return $rates;
    }
}
