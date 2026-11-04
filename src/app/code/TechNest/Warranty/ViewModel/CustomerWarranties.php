<?php
declare(strict_types=1);

namespace TechNest\Warranty\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use TechNest\Warranty\Api\Data\WarrantyInterface;
use TechNest\Warranty\Api\WarrantyRepositoryInterface;

/**
 * "My Warranties" in the customer account
 */
class CustomerWarranties implements ArgumentInterface
{
    /**
     * @param CustomerSession $customerSession
     * @param WarrantyRepositoryInterface $warrantyRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly WarrantyRepositoryInterface $warrantyRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Warranties of the signed-in customer, newest first
     *
     * @return WarrantyInterface[]
     */
    public function getWarranties(): array
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        if (!$customerId) {
            return [];
        }
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(WarrantyInterface::CUSTOMER_ID, $customerId)
            ->addSortOrder($this->sortOrderBuilder->setField(WarrantyInterface::WARRANTY_ID)->setDescendingDirection()
                ->create())
            ->create();
        return $this->warrantyRepository->getList($criteria)->getItems();
    }

    /**
     * Lookup page of one serial
     *
     * @param WarrantyInterface $warranty
     * @return string
     */
    public function getLookupUrl(WarrantyInterface $warranty): string
    {
        return $this->urlBuilder->getUrl('warranty', ['_query' => ['serial' => $warranty->getSerial()]]);
    }
}
