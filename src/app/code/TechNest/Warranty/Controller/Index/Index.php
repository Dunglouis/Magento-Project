<?php
declare(strict_types=1);

namespace TechNest\Warranty\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * /warranty: look a warranty up by serial number (?serial=...)
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     */
    public function __construct(private readonly PageFactory $pageFactory)
    {
    }

    /**
     * @inheritdoc
     */
    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Warranty Check'));
        return $page;
    }
}
