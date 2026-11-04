<?php
declare(strict_types=1);

namespace TechNest\Automation\Cron;

use Psr\Log\LoggerInterface;
use TechNest\Automation\Model\Config;
use TechNest\Automation\Model\FlashSaleCleanup;

/**
 * Cron: remove the special prices of ended flash sales
 */
class CleanupFlashSale
{
    /**
     * @param Config $config
     * @param FlashSaleCleanup $flashSaleCleanup
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly FlashSaleCleanup $flashSaleCleanup,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run the job
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isFlashSaleCleanupEnabled()) {
            return;
        }
        $skus = $this->flashSaleCleanup->run();
        if ($skus) {
            $this->logger->info('TechNest: flash sale ended for ' . implode(', ', $skus));
        }
    }
}
