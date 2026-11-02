<?php
declare(strict_types=1);

namespace TechNest\Warranty\Controller\Adminhtml\Warranty;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Sales > Warranties grid
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'TechNest_Warranty::warranty';

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(Context $context, private readonly PageFactory $pageFactory)
    {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): Page
    {
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->pageFactory->create();
        $page->setActiveMenu('TechNest_Warranty::warranty');
        $page->getConfig()->getTitle()->prepend((string)__('Warranties'));
        return $page;
    }
}
