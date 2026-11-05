<?php
declare(strict_types=1);

namespace TechNest\Mail\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Every store sender (Stores > Configuration > General > Store Email Addresses) is named "TechNest".
 * The addresses stay as they are; the real one comes from technest_mail/smtp/from_email (environment).
 */
class NameStoreSenders implements DataPatchInterface
{
    private const SENDER_NAME = 'TechNest';
    private const IDENTITIES = ['general', 'sales', 'support', 'custom1', 'custom2'];

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
        foreach (self::IDENTITIES as $identity) {
            $this->configWriter->save('trans_email/ident_' . $identity . '/name', self::SENDER_NAME);
        }
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
