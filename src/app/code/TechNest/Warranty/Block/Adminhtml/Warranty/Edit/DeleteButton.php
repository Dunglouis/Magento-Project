<?php
declare(strict_types=1);

namespace TechNest\Warranty\Block\Adminhtml\Warranty\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Delete" (existing warranties only)
 */
class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritdoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData(): array
    {
        if (!$this->getWarrantyId()) {
            return [];
        }
        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                __('Are you sure you want to delete this warranty?'),
                $this->getUrl('*/*/delete', ['warranty_id' => $this->getWarrantyId()])
            ),
            'sort_order' => 20,
        ];
    }
}
