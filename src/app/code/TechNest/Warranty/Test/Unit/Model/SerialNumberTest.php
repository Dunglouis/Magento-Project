<?php
declare(strict_types=1);

namespace TechNest\Warranty\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TechNest\Warranty\Model\SerialNumber;

class SerialNumberTest extends TestCase
{
    /**
     * @return array<string,array{string,bool}>
     */
    public static function serials(): array
    {
        return [
            'valid' => ['TN-2610-7KQ3ZP', true],
            'december' => ['TN-2512-ABCDEF', true],
            'lower case not normalized' => ['tn-2610-7kq3zp', false],
            'month 00' => ['TN-2600-7KQ3ZP', false],
            'month 13' => ['TN-2613-7KQ3ZP', false],
            'look-alike 0' => ['TN-2610-7KQ30P', false],
            'look-alike O' => ['TN-2610-7KQ3OP', false],
            'look-alike I' => ['TN-2610-7KQ3IP', false],
            'look-alike L' => ['TN-2610-7KQ3LP', false],
            'too short' => ['TN-2610-7KQ3Z', false],
            'too long' => ['TN-2610-7KQ3ZPX', false],
            'wrong prefix' => ['XX-2610-7KQ3ZP', false],
            'no dashes' => ['TN26107KQ3ZP', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('serials')]
    public function testIsValid(string $serial, bool $expected): void
    {
        self::assertSame($expected, (new SerialNumber())->isValid($serial));
    }

    public function testNormalizeUppercasesAndStripsSpaces(): void
    {
        self::assertSame('TN-2610-7KQ3ZP', (new SerialNumber())->normalize(" tn-2610 -7kq3zp\t"));
    }

    public function testGeneratedSerialsAreValidAndCarryTheStartMonth(): void
    {
        $serialNumber = new SerialNumber();
        $seen = [];
        for ($i = 0; $i < 200; $i++) {
            $serial = $serialNumber->generate('2026-10-29');
            self::assertTrue($serialNumber->isValid($serial), $serial);
            self::assertStringStartsWith('TN-2610-', $serial);
            $seen[$serial] = true;
        }
        self::assertGreaterThan(195, count($seen), 'serials should be random');
    }
}
