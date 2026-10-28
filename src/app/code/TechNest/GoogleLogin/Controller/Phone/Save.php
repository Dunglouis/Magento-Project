<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Controller\Phone;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use TechNest\CustomerAccess\Setup\Patch\Data\AddPhoneNumberAttribute;
use TechNest\GoogleLogin\Model\PhoneRequirement;

/**
 * Save the phone number (validated by TechNest_CustomerAccess on save), then continue like after sign-in
 */
class Save implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param PhoneRequirement $phoneRequirement
     * @param AccountRedirect $accountRedirect
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly PhoneRequirement $phoneRequirement,
        private readonly AccountRedirect $accountRedirect,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager
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
        $phone = trim((string)$this->request->getParam(AddPhoneNumberAttribute::ATTRIBUTE_CODE));
        if ($phone === '') {
            $this->messageManager->addErrorMessage(__('Please enter your phone number.'));
            return $this->redirectFactory->create()->setPath('technest_google/phone');
        }

        try {
            $customer = $this->customerRepository->getById((int)$this->customerSession->getCustomerId());
            $customer->setCustomAttribute(AddPhoneNumberAttribute::ATTRIBUTE_CODE, $phone);
            $this->customerRepository->save($customer);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->redirectFactory->create()->setPath('technest_google/phone');
        }

        $this->phoneRequirement->clear();
        $this->messageManager->addSuccessMessage(__('Your phone number has been saved.'));
        return $this->accountRedirect->getRedirect();
    }
}
