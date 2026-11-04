<?php
declare(strict_types=1);

namespace TechNest\Automation\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Automation settings (Stores > Configuration > TechNest > Automation)
 */
class Config
{
    private const XML_CANCEL_STALE = 'technest_automation/orders/cancel_stale_transfers';
    private const XML_TRANSFER_DAYS = 'technest_automation/orders/transfer_days';
    private const XML_FLASH_SALE_CLEANUP = 'technest_automation/flash_sale/cleanup';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Whether cron cancels unpaid bank transfer orders
     *
     * @return bool
     */
    public function isCancelStaleTransfersEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_CANCEL_STALE);
    }

    /**
     * Days a bank transfer order may wait for the payment
     *
     * @return int
     */
    public function getTransferDays(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_TRANSFER_DAYS));
    }

    /**
     * Whether cron cleans up ended flash sales
     *
     * @return bool
     */
    public function isFlashSaleCleanupEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_FLASH_SALE_CLEANUP);
    }
}
