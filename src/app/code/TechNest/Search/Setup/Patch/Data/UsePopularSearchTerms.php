<?php
declare(strict_types=1);

namespace TechNest\Search\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Autocomplete lists earlier search terms starting with what is typed ("sam" -> "samsung"),
 * instead of OpenSearch spelling suggestions, which return nothing for half-typed words.
 */
class UsePopularSearchTerms implements DataPatchInterface
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
        $this->configWriter->save('catalog/search/search_suggestion_enabled', '0');
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
