<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use TechNest\Warranty\Api\Data\WarrantyInterface;

/**
 * Warranty statuses
 */
class Status implements OptionSourceInterface
{
    /**
     * @inheritdoc
     *
     * @return array<int,array{value:string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->getLabels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }

    /**
     * Status => label
     *
     * @return array<string,\Magento\Framework\Phrase>
     */
    public function getLabels(): array
    {
        return [
            WarrantyInterface::STATUS_ACTIVE => __('In Warranty'),
            WarrantyInterface::STATUS_EXPIRED => __('Expired'),
            WarrantyInterface::STATUS_VOID => __('Voided'),
        ];
    }
}
