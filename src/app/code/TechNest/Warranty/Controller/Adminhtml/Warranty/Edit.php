<?php
declare(strict_types=1);

namespace TechNest\Warranty\Controller\Adminhtml\Warranty;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use TechNest\Warranty\Api\WarrantyRepositoryInterface;

/**
 * Warranty form (new or existing)
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'TechNest_Warranty::manage';

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param WarrantyRepositoryInterface $warrantyRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly WarrantyRepositoryInterface $warrantyRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('warranty_id');
        $title = __('New Warranty');
        if ($id) {
            try {
                $title = __('Warranty %1', $this->warrantyRepository->getById($id)->getSerial());
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->pageFactory->create();
        $page->setActiveMenu('TechNest_Warranty::warranty');
        $page->getConfig()->getTitle()->prepend((string)$title);
        return $page;
    }
}
