<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TechNest\CustomerAccess\Model\PhoneNumberValidator;

class PhoneNumberValidatorTest extends TestCase
{
    /**
     * @return array<string,array{string,bool}>
     */
    public static function numbers(): array
    {
        return [
            'viettel 09x' => ['0912345678', true],
            'viettel 03x' => ['0387654321', true],
            'vinaphone 08x' => ['0812345678', true],
            'mobifone 07x' => ['0701234567', true],
            'vietnamobile 05x' => ['0521234567', true],
            '+84 prefix' => ['+84912345678', true],
            'with spaces' => ['091 234 5678', true],
            'with dots' => ['091.234.5678', true],
            'landline 02x' => ['0241234567', false],
            'invalid network 01x' => ['0112345678', false],
            'too short' => ['091234567', false],
            'too long' => ['09123456789', false],
            '84 without plus' => ['84912345678', false],
            'letters' => ['09123abcde', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('numbers')]
    public function testIsValid(string $phone, bool $expected): void
    {
        self::assertSame($expected, (new PhoneNumberValidator())->isValid($phone));
    }

    public function testNormalizeStripsSeparators(): void
    {
        self::assertSame('0912345678', (new PhoneNumberValidator())->normalize(' 091-234.56 78 '));
    }
}
