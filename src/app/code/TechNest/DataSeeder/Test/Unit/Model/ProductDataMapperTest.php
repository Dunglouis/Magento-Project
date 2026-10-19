<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TechNest\DataSeeder\Model\ProductDataMapper;

class ProductDataMapperTest extends TestCase
{
    /**
     * @var ProductDataMapper
     */
    private ProductDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ProductDataMapper();
    }

    public function testSeedValuesWinOverApiAndBrandFallsBackToApi(): void
    {
        $data = $this->mapper->map(
            ['sku' => 'TN-AC-AIRPODS', 'set' => 'TechNest Accessory', 'price' => 119.5,
                'description' => ['vi' => 'Mô tả tiếng Việt'], 'attributes' => ['color' => 'White']],
            ['title' => 'Apple Airpods', 'description' => 'API text', 'price' => 129.99, 'stock' => 7,
                'brand' => 'Apple', 'weight' => 4, 'images' => ['https://cdn.example/1.webp']]
        );

        self::assertSame(['en' => 'Apple Airpods', 'vi' => 'Apple Airpods'], $data['name']);
        self::assertSame(['en' => 'API text', 'vi' => 'Mô tả tiếng Việt'], $data['description']);
        self::assertSame(119.5, $data['price']);
        self::assertSame(7, $data['qty']);
        self::assertSame(['color' => 'White', 'brand' => 'Apple'], $data['attributes']);
        self::assertSame(['https://cdn.example/1.webp'], $data['images']);
    }

    public function testSeedOnlyProductGetsDefaults(): void
    {
        $data = $this->mapper->map([
            'sku' => 'TN-PC-OFFICE', 'set' => 'TechNest PC', 'price' => 999, 'qty' => 3,
            'name' => ['en' => 'Dell OptiPlex 7020 Tower'], 'attributes' => ['brand' => 'Dell'],
        ]);

        self::assertSame('simple', $data['type']);
        self::assertSame('dell-optiplex-7020-tower', $data['url_key']);
        self::assertSame(999.0, $data['price']);
        self::assertSame(1.0, $data['weight']);
        self::assertSame([], $data['images']);
    }

    public function testApiBrandDoesNotOverrideSeedBrand(): void
    {
        $data = $this->mapper->map(
            ['sku' => 'TN-X', 'set' => 'TechNest Accessory', 'attributes' => ['brand' => 'Samsung']],
            ['title' => 'X', 'price' => 10, 'brand' => 'Other']
        );

        self::assertSame('Samsung', $data['attributes']['brand']);
    }

    public function testIcecatImagesPutMainFirstPrefer500AndLimit(): void
    {
        $images = $this->mapper->icecatImages(['Gallery' => [
            ['Pic' => 'https://ic/1.jpg', 'Pic500x500' => 'https://ic/1-500.jpg', 'IsMain' => 'N'],
            ['Pic' => 'https://ic/2.jpg', 'IsMain' => 'N'],
            ['Pic' => 'https://ic/main.jpg', 'Pic500x500' => 'https://ic/main-500.jpg', 'IsMain' => 'Y'],
            ['Pic' => 'https://ic/4.jpg', 'Pic500x500' => 'https://ic/4-500.jpg'],
            ['Pic' => 'https://ic/5.jpg', 'Pic500x500' => 'https://ic/5-500.jpg'],
        ]]);

        self::assertCount(ProductDataMapper::MAX_IMAGES, $images);
        self::assertSame('https://ic/main-500.jpg', $images[0]);
        self::assertContains('https://ic/2.jpg', $images);
        self::assertSame([], $this->mapper->icecatImages([]));
    }

    public function testIcecatFillsNameAndImagesButSeedImagesWin(): void
    {
        $icecat = [
            'GeneralInfo' => ['Title' => 'HP 420 Mouse', 'Description' => ['LongDesc' => '<p>Icecat text</p>']],
            'Gallery' => [['Pic500x500' => 'https://ic/hp-500.jpg', 'IsMain' => 'Y']],
        ];
        $seed = ['sku' => 'TN-AC-HP', 'set' => 'TechNest Accessory', 'price' => 25];

        $data = $this->mapper->map($seed, [], $icecat);
        self::assertSame('HP 420 Mouse', $data['name']['en']);
        self::assertSame('<p>Icecat text</p>', $data['description']['en']);
        self::assertSame(['https://ic/hp-500.jpg'], $data['images']);

        $data = $this->mapper->map($seed + ['images' => ['https://own/1.jpg']], [], $icecat);
        self::assertSame(['https://own/1.jpg'], $data['images']);
    }

    #[DataProvider('urlKeyProvider')]
    public function testUrlKey(string $name, string $expected): void
    {
        self::assertSame($expected, $this->mapper->urlKey($name));
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function urlKeyProvider(): array
    {
        return [
            ['HyperX Alloy Origins Core', 'hyperx-alloy-origins-core'],
            ['Samsung 45W Charger (USB-C, 1 port)', 'samsung-45w-charger-usb-c-1-port'],
            ['  Lenovo Legion T7 (RTX 4080)  ', 'lenovo-legion-t7-rtx-4080'],
        ];
    }

    /**
     * @param array<string,mixed> $seed
     */
    #[DataProvider('invalidSeedProvider')]
    public function testRejectsInvalidSeed(array $seed): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->map($seed);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function invalidSeedProvider(): array
    {
        return [
            'SKU without TN- prefix' => [['sku' => 'ABC-1', 'set' => 'TechNest PC', 'price' => 10]],
            'missing attribute set' => [['sku' => 'TN-1', 'price' => 10]],
            'missing price' => [['sku' => 'TN-1', 'set' => 'TechNest PC']],
        ];
    }
}
