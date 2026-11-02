<?php
declare(strict_types=1);

namespace TechNest\Shipping\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TechNest\Shipping\Model\Provinces;

class ProvincesTest extends TestCase
{
    /**
     * @var Provinces
     */
    private Provinces $provinces;

    protected function setUp(): void
    {
        $this->provinces = new Provinces();
    }

    public function testListsThirtyFourProvincesInThreeZones(): void
    {
        $all = $this->provinces->getAll();
        $zones = array_count_values(array_column($all, 'zone'));

        $this->assertCount(34, $all);
        $this->assertSame(
            [Provinces::NORTH => 15, Provinces::CENTRAL => 11, Provinces::SOUTH => 8],
            $zones
        );
    }

    public function testZoneByCode(): void
    {
        $this->assertSame(Provinces::NORTH, $this->provinces->getZoneByCode('HN'));
        $this->assertSame(Provinces::CENTRAL, $this->provinces->getZoneByCode('dn'));
        $this->assertSame(Provinces::SOUTH, $this->provinces->getZoneByCode(' HCM '));
        $this->assertNull($this->provinces->getZoneByCode('CA'));
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function names(): array
    {
        return [
            'vi name' => ['Hà Nội', Provinces::NORTH],
            'lower case' => ['hà nội', Provinces::NORTH],
            'no diacritics, no space' => ['Hanoi', Provinces::NORTH],
            'city prefix' => ['Thành phố Hồ Chí Minh', Provinces::SOUTH],
            'short prefix' => ['TP. HCM', Provinces::SOUTH],
            'english' => ['Ho Chi Minh City', Provinces::SOUTH],
            'province prefix' => ['Tỉnh Thanh Hoá', Provinces::CENTRAL],
            'd with stroke' => ['Đà Nẵng', Provinces::CENTRAL],
            'old province merged north' => ['Nam Định', Provinces::NORTH],
            'old province merged south' => ['Bình Dương', Provinces::SOUTH],
            'old name with dash' => ['Bà Rịa-Vũng Tàu', Provinces::SOUTH],
            'unknown' => ['Michigan', null],
            'empty' => ['  ', null],
        ];
    }

    #[DataProvider('names')]
    public function testZoneByName(string $name, ?string $zone): void
    {
        $this->assertSame($zone, $this->provinces->getZoneByName($name));
    }
}
