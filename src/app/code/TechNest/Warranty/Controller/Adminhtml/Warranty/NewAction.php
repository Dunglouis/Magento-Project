<?php
declare(strict_types=1);

namespace TechNest\Warranty\Controller\Adminhtml\Warranty;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;

/**
 * New warranty: the edit form without an ID
 */
class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'TechNest_Warranty::manage';

    /**
     * @inheritdoc
     */
    public function execute(): Forward
    {
        /** @var Forward $forward */
        $forward = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_FORWARD);
        return $forward->forward('edit');
    }
}
