<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Directory\Model\ResourceModel\Currency as CurrencyResource;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Base currency USD, allowed USD/VND, vi store displays VND in vi_VN; store info = TechNest.
 */
class ConfigureLocaleAndCurrency implements DataPatchInterface
{
    private const USD_TO_VND = 25000;

    /**
     * @param WriterInterface $configWriter
     * @param StoreManagerInterface $storeManager
     * @param CurrencyResource $currencyResource
     */
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly StoreManagerInterface $storeManager,
        private readonly CurrencyResource $currencyResource
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        // Default scope (applies to en)
        $this->configWriter->save('currency/options/base', 'USD');
        $this->configWriter->save('currency/options/allow', 'USD,VND');
        $this->configWriter->save('currency/options/default', 'USD');
        $this->configWriter->save('general/locale/code', 'en_US');
        $this->configWriter->save('general/locale/timezone', 'Asia/Ho_Chi_Minh');
        $this->configWriter->save('general/country/default', 'VN');
        $this->configWriter->save('general/store_information/name', 'TechNest');
        $this->configWriter->save('general/store_information/country_id', 'VN');

        // vi store view
        $this->storeManager->reinitStores();
        $viId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();
        $this->configWriter->save('general/locale/code', 'vi_VN', ScopeInterface::SCOPE_STORES, $viId);
        $this->configWriter->save('currency/options/default', 'VND', ScopeInterface::SCOPE_STORES, $viId);

        $this->currencyResource->saveRates(['USD' => ['VND' => self::USD_TO_VND]]);

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
