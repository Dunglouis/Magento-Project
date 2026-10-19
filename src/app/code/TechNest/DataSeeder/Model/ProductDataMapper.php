<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

/**
 * Merges one seed entry with its optional DummyJSON / Open Icecat sources into the flat data ProductWriter saves.
 * Priority: seed file > DummyJSON > Icecat; Vietnamese falls back to English.
 */
class ProductDataMapper
{
    public const SKU_PREFIX = 'TN-';
    public const MAX_IMAGES = 4;

    /**
     * Normalized product data.
     *
     * @param array<string,mixed> $seed Entry from data/products/*.json
     * @param array<string,mixed> $api DummyJSON product, or [] when the entry has no dummyjson_id
     * @param array<string,mixed> $icecat Icecat "data" object, or [] when the entry has no icecat source
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     */
    public function map(array $seed, array $api = [], array $icecat = []): array
    {
        $sku = (string)($seed['sku'] ?? '');
        if (!str_starts_with($sku, self::SKU_PREFIX)) {
            throw new \InvalidArgumentException(sprintf('SKU "%s" must start with "%s"', $sku, self::SKU_PREFIX));
        }
        if (empty($seed['set'])) {
            throw new \InvalidArgumentException(sprintf('%s: "set" is required', $sku));
        }
        $price = (float)($seed['price'] ?? $api['price'] ?? 0);
        if ($price <= 0) {
            throw new \InvalidArgumentException(sprintf('%s: price must be greater than 0', $sku));
        }

        $info = $icecat['GeneralInfo'] ?? [];
        $nameEn = (string)($seed['name']['en'] ?? $api['title'] ?? $info['Title'] ?? $sku);
        $descriptionEn = (string)($seed['description']['en'] ?? $api['description']
            ?? $info['Description']['LongDesc'] ?? '');
        $attributes = $seed['attributes'] ?? [];
        if (!isset($attributes['brand']) && !empty($api['brand'])) {
            $attributes['brand'] = $api['brand'];
        }

        return [
            'sku' => $sku,
            'type' => $seed['type'] ?? 'simple',
            'set' => $seed['set'],
            'categories' => $seed['categories'] ?? [],
            'name' => ['en' => $nameEn, 'vi' => (string)($seed['name']['vi'] ?? $nameEn)],
            'description' => ['en' => $descriptionEn, 'vi' => (string)($seed['description']['vi'] ?? $descriptionEn)],
            'url_key' => $this->urlKey($nameEn),
            'price' => round($price, 2),
            'qty' => (int)($seed['qty'] ?? $api['stock'] ?? 0),
            'weight' => (float)($seed['weight'] ?? $api['weight'] ?? 1),
            'attributes' => $attributes,
            'attributes_vi' => $seed['attributes_vi'] ?? [],
            'images' => $seed['images'] ?? ($icecat ? $this->icecatImages($icecat) : ($api['images'] ?? [])),
        ];
    }

    /**
     * Icecat gallery URLs: main image first, 500x500 version, at most MAX_IMAGES.
     *
     * @param array<string,mixed> $icecat
     * @return string[]
     */
    public function icecatImages(array $icecat): array
    {
        $gallery = $icecat['Gallery'] ?? [];
        usort(
            $gallery,
            fn (array $a, array $b): int => (($b['IsMain'] ?? '') === 'Y') <=> (($a['IsMain'] ?? '') === 'Y')
        );
        $urls = array_filter(array_map(
            fn (array $item): string => (string)($item['Pic500x500'] ?? $item['Pic'] ?? ''),
            $gallery
        ));
        return array_slice(array_values($urls), 0, self::MAX_IMAGES);
    }

    /**
     * URL key from an English name: "Samsung 45W Charger (USB-C)" -> "samsung-45w-charger-usb-c".
     *
     * @param string $name
     * @return string
     */
    public function urlKey(string $name): string
    {
        return strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
    }
}
