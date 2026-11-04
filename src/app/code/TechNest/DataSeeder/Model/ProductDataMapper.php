<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

use Magento\Catalog\Model\Product\Visibility;

/**
 * Merges one seed entry with its optional DummyJSON / Open Icecat sources into the flat data ProductWriter saves.
 * Priority: seed file > DummyJSON > Icecat; Vietnamese falls back to English.
 */
class ProductDataMapper
{
    public const SKU_PREFIX = 'TN-';
    public const MAX_IMAGES = 4;
    /** Types whose price comes from their items, so the seed has no price */
    public const COMPOSITE_TYPES = ['bundle', 'grouped'];

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
        $type = (string)($seed['type'] ?? 'simple');
        $isComposite = in_array($type, self::COMPOSITE_TYPES, true);
        $price = (float)($seed['price'] ?? $api['price'] ?? 0);
        if (!$isComposite && $price <= 0) {
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
        $axes = array_keys($seed['configurable'] ?? []);
        $attributes = array_diff_key($attributes, array_flip($axes));

        return [
            'sku' => $sku,
            'type' => $type,
            'set' => $seed['set'],
            'categories' => $seed['categories'] ?? [],
            'name' => ['en' => $nameEn, 'vi' => (string)($seed['name']['vi'] ?? $nameEn)],
            'description' => ['en' => $descriptionEn, 'vi' => (string)($seed['description']['vi'] ?? $descriptionEn)],
            'url_key' => $this->urlKey($nameEn),
            'visibility' => ($seed['visibility'] ?? '') === 'search'
                ? Visibility::VISIBILITY_IN_SEARCH
                : Visibility::VISIBILITY_BOTH,
            'price' => round($price, 2),
            'qty' => (int)($seed['qty'] ?? $api['stock'] ?? 0),
            'weight' => (float)($seed['weight'] ?? $api['weight'] ?? 1),
            'attributes' => $attributes,
            'attributes_vi' => $seed['attributes_vi'] ?? [],
            'images' => $seed['images'] ?? ($icecat ? $this->icecatImages($icecat) : ($api['images'] ?? [])),
            'configurable_attributes' => $axes,
            'type_data' => $this->typeData($type, $seed),
            'bundle' => $seed['bundle'] ?? [],
            'grouped' => $seed['grouped'] ?? [],
        ];
    }

    /**
     * Type-specific product fields saved as-is (they override the generic ones).
     *
     * @param string $type
     * @param array<string,mixed> $seed
     * @return array<string, mixed>
     */
    private function typeData(string $type, array $seed): array
    {
        return match ($type) {
            // No weight: virtual products are never shipped
            'virtual' => ['weight' => null, 'product_has_weight' => 0],
            // Dynamic price/SKU/weight; for dynamic bundles special_price is the percentage the customer pays
            'bundle' => [
                'price_type' => 0,
                'sku_type' => 0,
                'weight_type' => 0,
                'price_view' => 0,
                'shipment_type' => 0,
                'special_price' => 100 - (float)($seed['discount_percent'] ?? 0),
            ],
            default => [],
        };
    }

    /**
     * Child simple products of a configurable seed: every combination of its "configurable" option lists.
     *
     * An option is a label or {"value": label, "price": extra}; child price = parent price + extras.
     *
     * @param array<string,mixed> $parent Output of map() for the configurable product
     * @param array<string,mixed> $seed
     * @return array<int, array<string, mixed>>
     * @throws \InvalidArgumentException
     */
    public function mapVariants(array $parent, array $seed): array
    {
        $combinations = [[]];
        foreach ($seed['configurable'] ?? [] as $code => $options) {
            if (!is_array($options) || $options === []) {
                throw new \InvalidArgumentException(sprintf('%s: no options for "%s"', $parent['sku'], $code));
            }
            $next = [];
            foreach ($combinations as $combination) {
                foreach ($options as $option) {
                    $next[] = $combination + [$code => [
                        (string)(is_array($option) ? $option['value'] : $option),
                        (float)(is_array($option) ? ($option['price'] ?? 0) : 0),
                    ]];
                }
            }
            $combinations = $next;
        }
        if ($combinations === [[]]) {
            throw new \InvalidArgumentException(sprintf('%s: "configurable" is empty', $parent['sku']));
        }

        $variants = [];
        foreach ($combinations as $combination) {
            $labels = array_map(fn (array $pair): string => $pair[0], $combination);
            $suffix = implode('-', array_map(
                fn (string $label): string => strtoupper((string)preg_replace('/[^A-Za-z0-9]+/', '', $label)),
                $labels
            ));
            $title = ' - ' . implode(' / ', $labels);
            $variants[] = [
                'sku' => $parent['sku'] . '-' . $suffix,
                'type' => 'simple',
                'set' => $parent['set'],
                'categories' => [],
                'name' => ['en' => $parent['name']['en'] . $title, 'vi' => $parent['name']['vi'] . $title],
                'description' => $parent['description'],
                'url_key' => $parent['url_key'] . '-' . $this->urlKey(implode(' ', $labels)),
                'visibility' => Visibility::VISIBILITY_NOT_VISIBLE,
                'price' => round($parent['price'] + array_sum(array_column($combination, 1)), 2),
                'qty' => $parent['qty'],
                'weight' => $parent['weight'],
                'attributes' => $labels + $parent['attributes'],
                'attributes_vi' => $parent['attributes_vi'],
                'images' => [],
                'configurable_attributes' => [],
                'type_data' => [],
                'bundle' => [],
                'grouped' => [],
            ];
        }
        return $variants;
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
