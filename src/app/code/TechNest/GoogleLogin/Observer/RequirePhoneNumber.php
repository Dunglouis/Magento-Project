<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Observer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TechNest\GoogleLogin\Model\PhoneRequirement;

/**
 * While a Google customer still owes a phone number, every storefront page leads to the phone page
 */
class RequirePhoneNumber implements ObserverInterface
{
    private const ALLOWED_ACTIONS = [
        'technest_google_phone_index',
        'technest_google_phone_save',
        'customer_account_logout',
        'customer_account_logoutsuccess',
    ];

    /**
     * @param PhoneRequirement $phoneRequirement
     * @param CustomerSession $customerSession
     * @param ActionFlag $actionFlag
     * @param RedirectInterface $redirect
     */
    public function __construct(
        private readonly PhoneRequirement $phoneRequirement,
        private readonly CustomerSession $customerSession,
        private readonly ActionFlag $actionFlag,
        private readonly RedirectInterface $redirect
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        if (!$this->phoneRequirement->isRequired()) {
            return;
        }
        if (!$this->customerSession->isLoggedIn()) {
            $this->phoneRequirement->clear();
            return;
        }
        /** @var HttpRequest $request */
        $request = $observer->getData('request');
        $action = strtolower((string)$request->getFullActionName());
        // AJAX calls (private content, cart sections) keep working so the phone page renders normally
        if (in_array($action, self::ALLOWED_ACTIONS, true) || $request->isAjax()) {
            return;
        }
        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, '1');
        $this->redirect->redirect($observer->getData('controller_action')->getResponse(), 'technest_google/phone');
    }
}
