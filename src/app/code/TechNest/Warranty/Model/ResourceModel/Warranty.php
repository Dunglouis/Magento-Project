<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use TechNest\Warranty\Api\Data\WarrantyInterface;

/**
 * technest_warranty table
 */
class Warranty extends AbstractDb
{
    public const TABLE = 'technest_warranty';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, WarrantyInterface::WARRANTY_ID);
    }

    /**
     * Whether a serial number is already taken
     *
     * @param string $serial
     * @return bool
     */
    public function serialExists(string $serial): bool
    {
        $connection = $this->getConnection();
        return (bool)$connection->fetchOne(
            $connection->select()->from($this->getMainTable(), 'warranty_id')->where('serial = ?', $serial)
        );
    }

    /**
     * Whether warranties were already issued for an order
     *
     * @param int $orderId
     * @return bool
     */
    public function hasOrder(int $orderId): bool
    {
        $connection = $this->getConnection();
        return (bool)$connection->fetchOne(
            $connection->select()->from($this->getMainTable(), 'warranty_id')->where('order_id = ?', $orderId)
        );
    }

    /**
     * Set the status of every warranty of an order that is not void yet
     *
     * @param int $orderId
     * @param string $status
     * @return int Rows changed
     */
    public function setOrderStatus(int $orderId, string $status): int
    {
        return $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => $status],
            ['order_id = ?' => $orderId, 'status <> ?' => WarrantyInterface::STATUS_VOID]
        );
    }

    /**
     * Active warranties whose last covered day is before the given date
     *
     * @param string $today Y-m-d
     * @return string[] Serial numbers
     */
    public function getExpiredSerials(string $today): array
    {
        $connection = $this->getConnection();
        return $connection->fetchCol(
            $connection->select()->from($this->getMainTable(), 'serial')
                ->where('status = ?', WarrantyInterface::STATUS_ACTIVE)
                ->where('expires_at < ?', $today)
        );
    }

    /**
     * Mark active warranties whose last covered day is before the given date as expired
     *
     * @param string $today Y-m-d
     * @return int Rows changed
     */
    public function markExpired(string $today): int
    {
        return $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => WarrantyInterface::STATUS_EXPIRED],
            ['status = ?' => WarrantyInterface::STATUS_ACTIVE, 'expires_at < ?' => $today]
        );
    }
}
