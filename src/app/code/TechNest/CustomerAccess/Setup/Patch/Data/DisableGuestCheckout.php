<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Guests may fill a cart, but placing an order needs an account (see Observer\RequireLoginForCheckout).
 */
class DisableGuestCheckout implements DataPatchInterface
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
        $this->configWriter->save('checkout/options/guest_checkout', '0');
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
