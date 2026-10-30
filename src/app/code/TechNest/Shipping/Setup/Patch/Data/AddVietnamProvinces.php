<?php
declare(strict_types=1);

namespace TechNest\Shipping\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use TechNest\Base\Setup\Patch\Data\CreateStoreViews;
use TechNest\Shipping\Model\Provinces;

/**
 * Adds the 34 provinces of Vietnam to the region list (English and Vietnamese names),
 * so checkout shows a province dropdown the regional carrier can price.
 * Also gives the carrier its Vietnamese title on the vi store view.
 */
class AddVietnamProvinces implements DataPatchInterface
{
    private const VI_CONFIG = [
        'carriers/technest_regional/title' => 'Giao hàng TechNest',
        'carriers/technest_regional/name' => 'Giao theo khu vực',
    ];

    /**
     * @param ResourceConnection $resource
     * @param Provinces $provinces
     * @param WriterInterface $configWriter
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Provinces $provinces,
        private readonly WriterInterface $configWriter,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $connection = $this->resource->getConnection();
        $regionTable = $this->resource->getTableName('directory_country_region');
        $nameTable = $this->resource->getTableName('directory_country_region_name');

        $existing = $connection->fetchPairs(
            $connection->select()
                ->from($regionTable, ['code', 'region_id'])
                ->where('country_id = ?', Provinces::COUNTRY)
        );

        foreach ($this->provinces->getAll() as $code => $province) {
            $regionId = (int)($existing[$code] ?? 0);
            if (!$regionId) {
                $connection->insert($regionTable, [
                    'country_id' => Provinces::COUNTRY,
                    'code' => $code,
                    'default_name' => $province['en'],
                ]);
                $regionId = (int)$connection->fetchOne(
                    $connection->select()
                        ->from($regionTable, 'region_id')
                        ->where('country_id = ?', Provinces::COUNTRY)
                        ->where('code = ?', $code)
                );
            }
            foreach (['en_US' => $province['en'], 'vi_VN' => $province['vi']] as $locale => $name) {
                $connection->insertOnDuplicate(
                    $nameTable,
                    ['locale' => $locale, 'region_id' => $regionId, 'name' => $name],
                    ['name']
                );
            }
        }

        $this->storeManager->reinitStores();
        $viId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();
        foreach (self::VI_CONFIG as $path => $value) {
            $this->configWriter->save($path, $value, ScopeInterface::SCOPE_STORES, $viId);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [CreateStoreViews::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
