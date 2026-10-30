<?php
declare(strict_types=1);

namespace TechNest\Warranty\Controller\Adminhtml\Warranty;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use TechNest\Warranty\Api\WarrantyRepositoryInterface;
use TechNest\Warranty\Model\ResourceModel\Warranty\CollectionFactory;

/**
 * Delete the warranties selected in the grid
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'TechNest_Warranty::manage';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param WarrantyRepositoryInterface $warrantyRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly WarrantyRepositoryInterface $warrantyRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): Redirect
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;
        foreach ($collection->getItems() as $warranty) {
            /** @var \TechNest\Warranty\Model\Warranty $warranty */
            $this->warrantyRepository->delete($warranty);
            $count++;
        }
        $this->messageManager->addSuccessMessage(__('A total of %1 warranties have been deleted.', $count));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
