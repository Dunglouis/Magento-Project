<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Guest greeting in the top bar (replaces "Default welcome msg!").
 */
class SetHeaderWelcome implements DataPatchInterface
{
    /**
     * @param WriterInterface $configWriter
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->configWriter->save('design/header/welcome', 'Welcome to TechNest');

        $this->storeManager->reinitStores();
        $viId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();
        $this->configWriter->save(
            'design/header/welcome',
            'Chào mừng bạn đến với TechNest',
            ScopeInterface::SCOPE_STORES,
            $viId
        );

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
