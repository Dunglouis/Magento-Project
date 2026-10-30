<?php
declare(strict_types=1);

namespace TechNest\Warranty\Controller\Adminhtml\Warranty;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use TechNest\Warranty\Api\WarrantyRepositoryInterface;

/**
 * Delete one warranty
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'TechNest_Warranty::manage';

    /**
     * @param Context $context
     * @param WarrantyRepositoryInterface $warrantyRepository
     */
    public function __construct(
        Context $context,
        private readonly WarrantyRepositoryInterface $warrantyRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');
        $id = (int)$this->getRequest()->getParam('warranty_id');
        try {
            $this->warrantyRepository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('The warranty has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect;
    }
}
