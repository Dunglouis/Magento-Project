<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * The header cart icon links straight to the cart page instead of opening the mini cart dropdown
 * (core setting "Display Shopping Cart Sidebar").
 */
class DisableMiniCartDropdown implements DataPatchInterface
{
    /**
     * @param WriterInterface $configWriter
     */
    public function __construct(
        private readonly WriterInterface $configWriter
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->configWriter->save('checkout/sidebar/display', '0');
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
