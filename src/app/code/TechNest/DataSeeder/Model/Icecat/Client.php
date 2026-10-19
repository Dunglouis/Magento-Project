<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Icecat;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Open Icecat live API (https://live.icecat.biz/api): product content by brand + manufacturer part number.
 * Successful responses are cached in var/technest_seed/icecat/ so re-runs do not hit the API again.
 */
class Client
{
    public const CONFIG_USERNAME = 'technest_seeder/icecat/username';
    private const API_URL = 'https://live.icecat.biz/api';
    private const CACHE_DIR = 'technest_seed/icecat/';
    private const TIMEOUT = 30;

    /**
     * @param CurlFactory $curlFactory
     * @param Filesystem $filesystem
     * @param Json $json
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Filesystem $filesystem,
        private readonly Json $json,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Raw Icecat "data" object (GeneralInfo, Gallery, FeaturesGroups...).
     *
     * @param string $brand
     * @param string $mpn
     * @return array<string, mixed>
     * @throws LocalizedException When the username is not set or Icecat has no open data for the product
     */
    public function getProduct(string $brand, string $mpn): array
    {
        $username = (string)$this->scopeConfig->getValue(self::CONFIG_USERNAME);
        if ($username === '') {
            throw new LocalizedException(
                __('Icecat username is not set: bin/magento config:set %1 <username>', self::CONFIG_USERNAME)
            );
        }

        $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $cacheFile = self::CACHE_DIR . preg_replace('/[^A-Za-z0-9._-]+/', '_', $brand . '_' . $mpn) . '.json';
        if ($varDir->isFile($cacheFile)) {
            $response = (array)$this->json->unserialize($varDir->readFile($cacheFile));
        } else {
            $curl = $this->curlFactory->create();
            $curl->setTimeout(self::TIMEOUT);
            $curl->get(self::API_URL . '?' . http_build_query([
                'UserName' => $username,
                'Language' => 'en',
                'Brand' => $brand,
                'ProductCode' => $mpn,
            ]));
            $response = (array)$this->json->unserialize($curl->getBody());
            if (isset($response['data'])) {
                $varDir->writeFile($cacheFile, $curl->getBody());
            }
        }

        if (!isset($response['data'])) {
            throw new LocalizedException(
                __('Icecat %1 %2: %3', $brand, $mpn, (string)($response['Message'] ?? 'no data'))
            );
        }
        return (array)$response['data'];
    }
}
