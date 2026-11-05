<?php
declare(strict_types=1);

namespace TechNest\Warranty\Controller\Adminhtml\Warranty;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use TechNest\Warranty\Api\Data\WarrantyInterface;
use TechNest\Warranty\Api\WarrantyRepositoryInterface;
use TechNest\Warranty\Model\WarrantyFactory;

/**
 * Save the warranty form. Empty serial: a new one is generated; empty end date: computed from the months.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'TechNest_Warranty::manage';
    public const PERSIST_KEY = 'technest_warranty';

    private const FIELDS = [
        WarrantyInterface::SERIAL,
        WarrantyInterface::ORDER_INCREMENT_ID,
        WarrantyInterface::SKU,
        WarrantyInterface::PRODUCT_NAME,
        WarrantyInterface::CUSTOMER_EMAIL,
        WarrantyInterface::CUSTOMER_NAME,
        WarrantyInterface::STARTS_AT,
        WarrantyInterface::MONTHS,
        WarrantyInterface::EXPIRES_AT,
        WarrantyInterface::STATUS,
        WarrantyInterface::NOTE,
    ];

    /**
     * @param Context $context
     * @param WarrantyRepositoryInterface $warrantyRepository
     * @param WarrantyFactory $warrantyFactory
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        private readonly WarrantyRepositoryInterface $warrantyRepository,
        private readonly WarrantyFactory $warrantyFactory,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->getRequest();
        $post = (array)$request->getPostValue();
        if (!$post) {
            return $redirect->setPath('*/*/');
        }
        $id = (int)($post[WarrantyInterface::WARRANTY_ID] ?? 0);

        try {
            $warranty = $id ? $this->warrantyRepository->getById($id) : $this->warrantyFactory->create();
            $data = [];
            foreach (self::FIELDS as $field) {
                $value = isset($post[$field]) ? trim((string)$post[$field]) : '';
                $data[$field] = $value === '' ? null : $value;
            }
            // A changed start or length recomputes the end date unless the admin typed a new one
            if ($id && $data[WarrantyInterface::EXPIRES_AT] === $warranty->getExpiresAt()
                && ($data[WarrantyInterface::STARTS_AT] !== $warranty->getStartsAt()
                    || (int)$data[WarrantyInterface::MONTHS] !== $warranty->getMonths())
            ) {
                $data[WarrantyInterface::EXPIRES_AT] = null;
            }
            /** @var \TechNest\Warranty\Model\Warranty $warranty */
            $warranty->addData($data);
            $this->warrantyRepository->save($warranty);
            $this->messageManager->addSuccessMessage(__('Warranty %1 has been saved.', $warranty->getSerial()));
            $this->dataPersistor->clear(self::PERSIST_KEY);
            return $this->getRequest()->getParam('back')
                ? $redirect->setPath('*/*/edit', ['warranty_id' => $warranty->getWarrantyId()])
                : $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                (string)__('Something went wrong while saving the warranty.')
            );
        }
        $this->dataPersistor->set(self::PERSIST_KEY, $post);
        return $id ? $redirect->setPath('*/*/edit', ['warranty_id' => $id]) : $redirect->setPath('*/*/new');
    }
}
