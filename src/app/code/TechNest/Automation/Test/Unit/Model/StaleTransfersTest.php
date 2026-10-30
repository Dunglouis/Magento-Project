<?php
declare(strict_types=1);

namespace TechNest\Automation\Test\Unit\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TechNest\Automation\Model\StaleTransfers;

class StaleTransfersTest extends TestCase
{
    public function testCutoffIsUtcDaysBeforeNow(): void
    {
        $now = new \DateTimeImmutable('2026-10-29 08:30:00', new \DateTimeZone('Asia/Ho_Chi_Minh'));

        self::assertSame('2026-10-26 01:30:00', $this->staleTransfers()->cutoff($now, 3));
        self::assertSame('2026-10-28 01:30:00', $this->staleTransfers()->cutoff($now, 1));
    }

    /**
     * @return StaleTransfers
     */
    private function staleTransfers(): StaleTransfers
    {
        return new StaleTransfers(
            $this->createStub(CollectionFactory::class),
            $this->createStub(OrderRepositoryInterface::class),
            $this->createStub(LoggerInterface::class)
        );
    }
}
