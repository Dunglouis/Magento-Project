<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\Theme\Registration;

/**
 * Assigns the TechNest/minimal theme and sets TechNest head/footer defaults.
 */
class ApplyTechNestTheme implements DataPatchInterface
{
    public const THEME_PATH = 'frontend/TechNest/minimal';

    /**
     * @param WriterInterface $configWriter
     * @param StoreManagerInterface $storeManager
     * @param Registration $themeRegistration
     * @param ThemeProviderInterface $themeProvider
     */
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly StoreManagerInterface $storeManager,
        private readonly Registration $themeRegistration,
        private readonly ThemeProviderInterface $themeProvider
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        // Make sure the new theme exists in the `theme` table before assigning it
        $this->themeRegistration->register();
        $theme = $this->themeProvider->getThemeByFullPath(self::THEME_PATH);
        if (!$theme->getId()) {
            throw new LocalizedException(__('Theme %1 is not registered.', self::THEME_PATH));
        }

        $this->configWriter->save('design/theme/theme_id', (string)$theme->getId());
        // Sample data leftover: <link href="{{MEDIA_URL}}styles.css">
        $this->configWriter->delete('design/head/includes');
        $this->configWriter->save('design/head/default_title', 'TechNest');
        $this->configWriter->save('design/head/title_suffix', '| TechNest');
        $this->configWriter->save('design/footer/copyright', '© TechNest. Technology, simplified.');

        $this->storeManager->reinitStores();
        $viId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();
        $this->configWriter->save(
            'design/footer/copyright',
            '© TechNest. Công nghệ, gọn gàng.',
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
