<?php
declare(strict_types=1);

namespace TechNest\Warranty\Api\Data;

/**
 * Warranty of one product unit, identified by its serial number
 *
 * @api
 */
interface WarrantyInterface
{
    public const WARRANTY_ID = 'warranty_id';
    public const SERIAL = 'serial';
    public const ORDER_ID = 'order_id';
    public const ORDER_INCREMENT_ID = 'order_increment_id';
    public const SKU = 'sku';
    public const PRODUCT_NAME = 'product_name';
    public const CUSTOMER_ID = 'customer_id';
    public const CUSTOMER_EMAIL = 'customer_email';
    public const CUSTOMER_NAME = 'customer_name';
    public const STARTS_AT = 'starts_at';
    public const MONTHS = 'months';
    public const EXPIRES_AT = 'expires_at';
    public const STATUS = 'status';
    public const NOTE = 'note';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_VOID = 'void';

    /**
     * Warranty ID
     *
     * @return int|null
     */
    public function getWarrantyId(): ?int;

    /**
     * Serial number, e.g. TN-2610-7KQ3ZP
     *
     * @return string
     */
    public function getSerial(): string;

    /**
     * Set serial number
     *
     * @param string $serial
     * @return $this
     */
    public function setSerial(string $serial): self;

    /**
     * Order ID, null for warranties entered by hand
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Set order ID
     *
     * @param int|null $orderId
     * @return $this
     */
    public function setOrderId(?int $orderId): self;

    /**
     * Order number
     *
     * @return string|null
     */
    public function getOrderIncrementId(): ?string;

    /**
     * Set order number
     *
     * @param string|null $incrementId
     * @return $this
     */
    public function setOrderIncrementId(?string $incrementId): self;

    /**
     * Product SKU
     *
     * @return string
     */
    public function getSku(): string;

    /**
     * Set product SKU
     *
     * @param string $sku
     * @return $this
     */
    public function setSku(string $sku): self;

    /**
     * Product name
     *
     * @return string
     */
    public function getProductName(): string;

    /**
     * Set product name
     *
     * @param string $name
     * @return $this
     */
    public function setProductName(string $name): self;

    /**
     * Customer ID, null for guests or deleted customers
     *
     * @return int|null
     */
    public function getCustomerId(): ?int;

    /**
     * Set customer ID
     *
     * @param int|null $customerId
     * @return $this
     */
    public function setCustomerId(?int $customerId): self;

    /**
     * Customer email
     *
     * @return string|null
     */
    public function getCustomerEmail(): ?string;

    /**
     * Set customer email
     *
     * @param string|null $email
     * @return $this
     */
    public function setCustomerEmail(?string $email): self;

    /**
     * Customer name
     *
     * @return string|null
     */
    public function getCustomerName(): ?string;

    /**
     * Set customer name
     *
     * @param string|null $name
     * @return $this
     */
    public function setCustomerName(?string $name): self;

    /**
     * First day of cover (Y-m-d, store time zone)
     *
     * @return string
     */
    public function getStartsAt(): string;

    /**
     * Set first day of cover
     *
     * @param string $date Y-m-d
     * @return $this
     */
    public function setStartsAt(string $date): self;

    /**
     * Cover length in months
     *
     * @return int
     */
    public function getMonths(): int;

    /**
     * Set cover length in months
     *
     * @param int $months
     * @return $this
     */
    public function setMonths(int $months): self;

    /**
     * Last day of cover (Y-m-d, inclusive)
     *
     * @return string
     */
    public function getExpiresAt(): string;

    /**
     * Set last day of cover
     *
     * @param string $date Y-m-d
     * @return $this
     */
    public function setExpiresAt(string $date): self;

    /**
     * Status: active, expired or void
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * Admin note
     *
     * @return string|null
     */
    public function getNote(): ?string;

    /**
     * Set admin note
     *
     * @param string|null $note
     * @return $this
     */
    public function setNote(?string $note): self;

    /**
     * Created at (UTC)
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Updated at (UTC)
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
