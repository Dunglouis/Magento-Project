<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * technest_google_account: which customer a Google account (sub) signs in as
 */
class GoogleAccount extends AbstractDb
{
    public const TABLE = 'technest_google_account';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'entity_id');
    }

    /**
     * Customer linked to a Google account, or null
     *
     * @param string $sub
     * @return int|null
     */
    public function getCustomerIdBySub(string $sub): ?int
    {
        $connection = $this->getConnection();
        $id = $connection->fetchOne(
            $connection->select()->from($this->getMainTable(), 'customer_id')->where('google_sub = ?', $sub)
        );
        return is_numeric($id) ? (int)$id : null;
    }

    /**
     * Google account linked to a customer, or null
     *
     * @param int $customerId
     * @return string|null
     */
    public function getSubByCustomerId(int $customerId): ?string
    {
        $connection = $this->getConnection();
        $sub = $connection->fetchOne(
            $connection->select()->from($this->getMainTable(), 'google_sub')->where('customer_id = ?', $customerId)
        );
        return is_string($sub) && $sub !== '' ? $sub : null;
    }

    /**
     * Link a Google account to a customer
     *
     * @param string $sub
     * @param int $customerId
     * @param string $email
     * @return void
     */
    public function link(string $sub, int $customerId, string $email): void
    {
        $this->getConnection()->insert($this->getMainTable(), [
            'google_sub' => $sub,
            'customer_id' => $customerId,
            'email' => $email,
        ]);
    }
}
