<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Warranty settings (Stores > Configuration > TechNest > Warranty)
 */
class Config
{
    private const XML_DEFAULT_MONTHS = 'technest_warranty/general/default_months';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Cover length for products without their own "warranty_months"
     *
     * @return int
     */
    public function getDefaultMonths(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_DEFAULT_MONTHS));
    }
}
