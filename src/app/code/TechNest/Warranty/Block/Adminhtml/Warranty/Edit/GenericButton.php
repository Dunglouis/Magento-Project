<?php
declare(strict_types=1);

namespace TechNest\Warranty\Block\Adminhtml\Warranty\Edit;

use Magento\Backend\Block\Widget\Context;

/**
 * Shared helpers of the warranty form buttons
 */
class GenericButton
{
    /**
     * @param Context $context
     */
    public function __construct(protected readonly Context $context)
    {
    }

    /**
     * Warranty being edited, 0 for a new one
     *
     * @return int
     */
    public function getWarrantyId(): int
    {
        return (int)$this->context->getRequest()->getParam('warranty_id');
    }

    /**
     * Admin URL
     *
     * @param string $route
     * @param array<string,mixed> $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
