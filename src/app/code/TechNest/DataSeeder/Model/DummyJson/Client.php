<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\DummyJson;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Reads products from https://dummyjson.com. Each response is cached in var/technest_seed/dummyjson/
 * so re-runs work offline and do not hit the API again.
 */
class Client
{
    private const BASE_URL = 'https://dummyjson.com/products/';
    private const CACHE_DIR = 'technest_seed/dummyjson/';
    private const TIMEOUT = 20;

    /**
     * @param CurlFactory $curlFactory
     * @param Filesystem $filesystem
     * @param Json $json
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Filesystem $filesystem,
        private readonly Json $json
    ) {
    }

    /**
     * One DummyJSON product by id.
     *
     * @param int $id
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getProduct(int $id): array
    {
        $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $cacheFile = self::CACHE_DIR . $id . '.json';
        if ($varDir->isFile($cacheFile)) {
            return (array)$this->json->unserialize($varDir->readFile($cacheFile));
        }

        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::TIMEOUT);
        $curl->get(self::BASE_URL . $id);
        if ($curl->getStatus() !== 200) {
            throw new LocalizedException(__('DummyJSON product %1: HTTP %2', $id, $curl->getStatus()));
        }
        $body = $curl->getBody();
        $data = (array)$this->json->unserialize($body);
        $varDir->writeFile($cacheFile, $body);
        return $data;
    }
}
