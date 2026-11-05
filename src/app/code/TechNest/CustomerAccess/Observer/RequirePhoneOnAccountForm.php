<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Observer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use TechNest\CustomerAccess\Setup\Patch\Data\AddPhoneNumberAttribute;

/**
 * Storefront register and edit forms: the phone number is required (format is checked on save).
 */
class RequirePhoneOnAccountForm implements ObserverInterface
{
    private const BACK_PATHS = [
        'customer_account_createpost' => 'customer/account/create',
        'customer_account_editpost' => 'customer/account/edit',
    ];

    /**
     * @param CustomerSession $customerSession
     * @param ManagerInterface $messageManager
     * @param ActionFlag $actionFlag
     * @param RedirectInterface $redirect
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly ManagerInterface $messageManager,
        private readonly ActionFlag $actionFlag,
        private readonly RedirectInterface $redirect
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        /** @var HttpRequest $request */
        $request = $observer->getData('request');
        $backPath = self::BACK_PATHS[strtolower((string)$request->getFullActionName())] ?? null;
        if ($backPath === null
            || !$request->isPost()
            || trim((string)$request->getParam(AddPhoneNumberAttribute::ATTRIBUTE_CODE)) !== ''
        ) {
            return;
        }

        $this->messageManager->addErrorMessage(__('Please enter your phone number.'));
        $this->customerSession->setCustomerFormData($request->getPostValue());
        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, '1');
        $this->redirect->redirect($observer->getData('controller_action')->getResponse(), $backPath);
    }
}
