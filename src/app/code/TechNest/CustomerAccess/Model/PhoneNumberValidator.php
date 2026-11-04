<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Model;

/**
 * Vietnamese mobile numbers: 0 or +84, then a 3/5/7/8/9 network prefix and 8 digits (e.g. 0912345678).
 */
class PhoneNumberValidator
{
    /** Same pattern is used client-side (data-validate "pattern" rule), so keep it JS-compatible */
    public const PATTERN = '^(0|\+84)(3|5|7|8|9)\d{8}$';

    /**
     * Whether the number is a valid Vietnamese mobile number (spaces, dots and dashes are ignored)
     *
     * @param string $phone
     * @return bool
     */
    public function isValid(string $phone): bool
    {
        return (bool)preg_match('/' . self::PATTERN . '/', $this->normalize($phone));
    }

    /**
     * Strip the separators people often type: "091 234 5678", "091.234.5678", "091-234-5678"
     *
     * @param string $phone
     * @return string
     */
    public function normalize(string $phone): string
    {
        return (string)preg_replace('/[\s.\-]/', '', trim($phone));
    }
}
