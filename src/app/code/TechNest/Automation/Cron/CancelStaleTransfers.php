<?php
declare(strict_types=1);

namespace TechNest\Automation\Cron;

use Psr\Log\LoggerInterface;
use TechNest\Automation\Model\Config;
use TechNest\Automation\Model\StaleTransfers;

/**
 * Cron: cancel bank transfer orders unpaid after the waiting time
 */
class CancelStaleTransfers
{
    /**
     * @param Config $config
     * @param StaleTransfers $staleTransfers
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly StaleTransfers $staleTransfers,
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
        if (!$this->config->isCancelStaleTransfersEnabled()) {
            return;
        }
        $cancelled = $this->staleTransfers->cancel($this->config->getTransferDays());
        if ($cancelled) {
            $this->logger->info('TechNest: cancelled unpaid bank transfer orders ' . implode(', ', $cancelled));
        }
    }
}
