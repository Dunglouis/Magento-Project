<?php
declare(strict_types=1);

namespace TechNest\Automation\Cron;

use Psr\Log\LoggerInterface;
use TechNest\Automation\Model\WarrantyExpiry;

/**
 * Cron: mark ended warranties as expired
 */
class ExpireWarranties
{
    /**
     * @param WarrantyExpiry $warrantyExpiry
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly WarrantyExpiry $warrantyExpiry,
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
        $expired = $this->warrantyExpiry->run();
        if ($expired) {
            $this->logger->info(sprintf('TechNest: %d warranties expired', count($expired)));
        }
    }
}
