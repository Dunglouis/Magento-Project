<?php
declare(strict_types=1);

namespace TechNest\Warranty\Block\Adminhtml\Warranty\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Save and Continue Edit"
 */
class SaveAndContinueButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritdoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Save and Continue Edit'),
            'class' => 'save',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'saveAndContinueEdit']],
            ],
            'sort_order' => 80,
        ];
    }
}
