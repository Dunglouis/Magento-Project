<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model;

/**
 * Warranty serial numbers: "TN-" + year and month the cover starts (YYMM) + "-" + 6 random characters.
 * The random part skips look-alike characters (0/O, 1/I/L) so serials can be read out over the phone.
 */
class SerialNumber
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const RANDOM_LENGTH = 6;
    private const PATTERN = '/^TN-\d{2}(0[1-9]|1[0-2])-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{6}$/';

    /**
     * New random serial number
     *
     * @param string $startsAt Y-m-d
     * @return string
     */
    public function generate(string $startsAt): string
    {
        $random = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::RANDOM_LENGTH; $i++) {
            $random .= self::ALPHABET[random_int(0, $max)];
        }
        return sprintf('TN-%s%s-%s', substr($startsAt, 2, 2), substr($startsAt, 5, 2), $random);
    }

    /**
     * Upper case without spaces, as typed by a customer
     *
     * @param string $serial
     * @return string
     */
    public function normalize(string $serial): string
    {
        return strtoupper((string)preg_replace('/\s+/', '', $serial));
    }

    /**
     * Whether a (normalized) serial number has the TechNest format
     *
     * @param string $serial
     * @return bool
     */
    public function isValid(string $serial): bool
    {
        return (bool)preg_match(self::PATTERN, $serial);
    }
}
