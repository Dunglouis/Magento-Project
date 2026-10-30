<?php
declare(strict_types=1);

namespace TechNest\Warranty\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use TechNest\Warranty\Api\Data\WarrantyInterface;
use TechNest\Warranty\Api\WarrantyRepositoryInterface;
use TechNest\Warranty\Model\CoverPeriod;
use TechNest\Warranty\Model\SerialNumber;
use TechNest\Warranty\Model\Source\Status;

/**
 * Warranty lookup page: the serial typed by the shopper and what was found.
 * Shows no email and only a masked name, as anyone with a serial can look it up.
 */
class Lookup implements ArgumentInterface
{
    /**
     * @var WarrantyInterface|null
     */
    private ?WarrantyInterface $warranty = null;

    /**
     * @var bool
     */
    private bool $searched = false;

    /**
     * @param RequestInterface $request
     * @param WarrantyRepositoryInterface $warrantyRepository
     * @param SerialNumber $serialNumber
     * @param CoverPeriod $coverPeriod
     * @param Status $status
     * @param TimezoneInterface $timezone
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly WarrantyRepositoryInterface $warrantyRepository,
        private readonly SerialNumber $serialNumber,
        private readonly CoverPeriod $coverPeriod,
        private readonly Status $status,
        private readonly TimezoneInterface $timezone,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Form action
     *
     * @return string
     */
    public function getActionUrl(): string
    {
        return $this->urlBuilder->getUrl('warranty');
    }

    /**
     * Serial as typed, normalized
     *
     * @return string
     */
    public function getSerial(): string
    {
        return $this->serialNumber->normalize((string)$this->request->getParam('serial'));
    }

    /**
     * Message to show after a search that found nothing, '' otherwise
     *
     * @return string
     */
    public function getError(): string
    {
        $serial = $this->getSerial();
        if ($serial === '') {
            return '';
        }
        if (!$this->serialNumber->isValid($serial)) {
            return (string)__('"%1" is not a valid serial number (format TN-YYMM-XXXXXX).', $serial);
        }
        return $this->getWarranty() ? '' : (string)__('No warranty was found for serial number "%1".', $serial);
    }

    /**
     * Warranty matching the serial, or null
     *
     * @return WarrantyInterface|null
     */
    public function getWarranty(): ?WarrantyInterface
    {
        if (!$this->searched) {
            $this->searched = true;
            $serial = $this->getSerial();
            if ($this->serialNumber->isValid($serial)) {
                try {
                    $this->warranty = $this->warrantyRepository->getBySerial($serial);
                } catch (NoSuchEntityException) {
                    $this->warranty = null;
                }
            }
        }
        return $this->warranty;
    }

    /**
     * Status label
     *
     * @param WarrantyInterface $warranty
     * @return string
     */
    public function getStatusLabel(WarrantyInterface $warranty): string
    {
        return (string)($this->status->getLabels()[$warranty->getStatus()] ?? $warranty->getStatus());
    }

    /**
     * Days of cover left (0 when expired or void)
     *
     * @param WarrantyInterface $warranty
     * @return int
     */
    public function getDaysLeft(WarrantyInterface $warranty): int
    {
        return $warranty->getStatus() === WarrantyInterface::STATUS_VOID
            ? 0
            : $this->coverPeriod->daysLeft($warranty->getExpiresAt(), $this->timezone->date()->format('Y-m-d'));
    }

    /**
     * Date in the store locale's medium format
     *
     * @param string $date Y-m-d
     * @return string
     */
    public function formatDate(string $date): string
    {
        return $this->timezone->formatDate(new \DateTime($date), \IntlDateFormatter::MEDIUM);
    }

    /**
     * Customer name with only the first letter of each word, e.g. "N*** V** A"
     *
     * @param string|null $name
     * @return string
     */
    public function maskName(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string)$name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(' ', array_map(
            static fn (string $word): string => mb_substr($word, 0, 1) . str_repeat('*', mb_strlen($word) - 1),
            $words
        ));
    }
}
