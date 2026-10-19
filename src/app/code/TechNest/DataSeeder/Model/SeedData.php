<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Reads seed JSON files shipped in the module's data/ folder.
 */
class SeedData
{
    /**
     * @param ModuleDirReader $moduleDirReader
     * @param File $file
     * @param Json $json
     */
    public function __construct(
        private readonly ModuleDirReader $moduleDirReader,
        private readonly File $file,
        private readonly Json $json
    ) {
    }

    /**
     * Category tree: [{url_key, name: {en, vi}, children: [...]}].
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(): array
    {
        return $this->read('categories.json');
    }

    /**
     * Attribute definitions and attribute sets.
     *
     * @return array<string, mixed> {attributes: {code: spec}, sets: {name: [codes]}}
     */
    public function getAttributes(): array
    {
        return $this->read('attributes.json');
    }

    /**
     * URL paths of every seeded category, e.g. ["laptops", "laptops/gaming", ...].
     *
     * @return string[]
     */
    public function getCategoryUrlPaths(): array
    {
        $paths = [];
        $walk = function (array $nodes, string $prefix) use (&$walk, &$paths): void {
            foreach ($nodes as $node) {
                $path = $prefix . $node['url_key'];
                $paths[] = $path;
                $walk($node['children'] ?? [], $path . '/');
            }
        };
        $walk($this->getCategories(), '');
        return $paths;
    }

    /**
     * Product entries from every data/products/*.json file, in file name order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(): array
    {
        $files = $this->file->readDirectory($this->getDataDir() . '/products');
        sort($files);
        $products = [];
        foreach ($files as $path) {
            if (str_ends_with($path, '.json')) {
                $products[] = (array)$this->json->unserialize($this->file->fileGetContents($path));
            }
        }
        return array_merge([], ...$products);
    }

    /**
     * Decode one JSON file from data/.
     *
     * @param string $fileName
     * @return array<int|string, mixed>
     */
    private function read(string $fileName): array
    {
        return (array)$this->json->unserialize($this->file->fileGetContents($this->getDataDir() . '/' . $fileName));
    }

    /**
     * Absolute path of the module's data/ folder.
     *
     * @return string
     */
    private function getDataDir(): string
    {
        return $this->moduleDirReader->getModuleDir('', 'TechNest_DataSeeder') . '/data';
    }
}
