<?php
declare(strict_types=1);

namespace TechNest\Warranty\Controller\Customer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * /warranty/customer: warranties of the signed-in customer; guests go to sign-in and come back here
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param CustomerSession $customerSession
     * @param RedirectFactory $redirectFactory
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly CustomerSession $customerSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            $this->customerSession->setBeforeAuthUrl($this->urlBuilder->getUrl('warranty/customer'));
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('My Warranties'));
        return $page;
    }
}
