<?php
declare(strict_types=1);

namespace TechNest\Warranty\Block\Adminhtml\Warranty\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Save" (back to the grid)
 */
class SaveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritdoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Save'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}
