<?php
declare(strict_types=1);

namespace TechNest\Automation\Model;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use TechNest\Warranty\Model\ResourceModel\Warranty as WarrantyResource;

/**
 * Active warranties whose last covered day has passed (store time zone) become "expired"
 */
class WarrantyExpiry
{
    /**
     * @param WarrantyResource $warrantyResource
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly WarrantyResource $warrantyResource,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Expire the ended warranties
     *
     * @param bool $dryRun Only list them
     * @return string[] Serial numbers expired (or that would be)
     */
    public function run(bool $dryRun = false): array
    {
        $today = $this->timezone->date()->format('Y-m-d');
        $serials = $this->warrantyResource->getExpiredSerials($today);
        if (!$dryRun && $serials) {
            $this->warrantyResource->markExpired($today);
        }
        return $serials;
    }
}
