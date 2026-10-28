<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Controller\Phone;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;

/**
 * technest_google/phone: "Add your phone number" page after the first Google sign-in
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param CustomerSession $customerSession
     * @param PageFactory $pageFactory
     * @param RedirectFactory $redirectFactory
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Add your phone number'));
        return $page;
    }
}
