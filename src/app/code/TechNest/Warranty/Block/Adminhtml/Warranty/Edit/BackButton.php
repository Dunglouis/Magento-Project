<?php
declare(strict_types=1);

namespace TechNest\Warranty\Block\Adminhtml\Warranty\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Back" to the grid
 */
class BackButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritdoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Back'),
            'on_click' => sprintf("location.href = '%s';", $this->getUrl('*/*/')),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
