<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use TechNest\Warranty\Api\Data\WarrantyInterface;
use TechNest\Warranty\Model\ResourceModel\Warranty as WarrantyResource;

/**
 * Warranty record (technest_warranty)
 */
class Warranty extends AbstractModel implements WarrantyInterface, IdentityInterface
{
    public const CACHE_TAG = 'technest_warranty';

    /**
     * @var string
     */
    protected $_eventPrefix = 'technest_warranty';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(WarrantyResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @inheritdoc
     */
    public function getWarrantyId(): ?int
    {
        $id = $this->getData(self::WARRANTY_ID);
        return $id === null ? null : (int)$id;
    }

    /**
     * @inheritdoc
     */
    public function getSerial(): string
    {
        return (string)$this->getData(self::SERIAL);
    }

    /**
     * @inheritdoc
     */
    public function setSerial(string $serial): WarrantyInterface
    {
        return $this->setData(self::SERIAL, $serial);
    }

    /**
     * @inheritdoc
     */
    public function getOrderId(): ?int
    {
        return $this->nullableInt(self::ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setOrderId(?int $orderId): WarrantyInterface
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritdoc
     */
    public function getOrderIncrementId(): ?string
    {
        return $this->nullableString(self::ORDER_INCREMENT_ID);
    }

    /**
     * @inheritdoc
     */
    public function setOrderIncrementId(?string $incrementId): WarrantyInterface
    {
        return $this->setData(self::ORDER_INCREMENT_ID, $incrementId);
    }

    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return (string)$this->getData(self::SKU);
    }

    /**
     * @inheritdoc
     */
    public function setSku(string $sku): WarrantyInterface
    {
        return $this->setData(self::SKU, $sku);
    }

    /**
     * @inheritdoc
     */
    public function getProductName(): string
    {
        return (string)$this->getData(self::PRODUCT_NAME);
    }

    /**
     * @inheritdoc
     */
    public function setProductName(string $name): WarrantyInterface
    {
        return $this->setData(self::PRODUCT_NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerId(): ?int
    {
        return $this->nullableInt(self::CUSTOMER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerId(?int $customerId): WarrantyInterface
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerEmail(): ?string
    {
        return $this->nullableString(self::CUSTOMER_EMAIL);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerEmail(?string $email): WarrantyInterface
    {
        return $this->setData(self::CUSTOMER_EMAIL, $email);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerName(): ?string
    {
        return $this->nullableString(self::CUSTOMER_NAME);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerName(?string $name): WarrantyInterface
    {
        return $this->setData(self::CUSTOMER_NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function getStartsAt(): string
    {
        return substr((string)$this->getData(self::STARTS_AT), 0, 10);
    }

    /**
     * @inheritdoc
     */
    public function setStartsAt(string $date): WarrantyInterface
    {
        return $this->setData(self::STARTS_AT, $date);
    }

    /**
     * @inheritdoc
     */
    public function getMonths(): int
    {
        return (int)$this->getData(self::MONTHS);
    }

    /**
     * @inheritdoc
     */
    public function setMonths(int $months): WarrantyInterface
    {
        return $this->setData(self::MONTHS, $months);
    }

    /**
     * @inheritdoc
     */
    public function getExpiresAt(): string
    {
        return substr((string)$this->getData(self::EXPIRES_AT), 0, 10);
    }

    /**
     * @inheritdoc
     */
    public function setExpiresAt(string $date): WarrantyInterface
    {
        return $this->setData(self::EXPIRES_AT, $date);
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): string
    {
        return (string)($this->getData(self::STATUS) ?: self::STATUS_ACTIVE);
    }

    /**
     * @inheritdoc
     */
    public function setStatus(string $status): WarrantyInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritdoc
     */
    public function getNote(): ?string
    {
        return $this->nullableString(self::NOTE);
    }

    /**
     * @inheritdoc
     */
    public function setNote(?string $note): WarrantyInterface
    {
        return $this->setData(self::NOTE, $note);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->nullableString(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->nullableString(self::UPDATED_AT);
    }

    /**
     * Integer field or null when empty
     *
     * @param string $key
     * @return int|null
     */
    private function nullableInt(string $key): ?int
    {
        $value = $this->getData($key);
        return $value === null || $value === '' ? null : (int)$value;
    }

    /**
     * String field or null when empty
     *
     * @param string $key
     * @return string|null
     */
    private function nullableString(string $key): ?string
    {
        $value = $this->getData($key);
        return $value === null || $value === '' ? null : (string)$value;
    }
}
