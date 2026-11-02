<?php
declare(strict_types=1);

namespace TechNest\Warranty\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use TechNest\Warranty\Api\Data\WarrantyInterface;
use TechNest\Warranty\Api\Data\WarrantySearchResultsInterface;

/**
 * Warranty CRUD
 *
 * @api
 */
interface WarrantyRepositoryInterface
{
    /**
     * Save a warranty
     *
     * @param \TechNest\Warranty\Api\Data\WarrantyInterface $warranty
     * @return \TechNest\Warranty\Api\Data\WarrantyInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(WarrantyInterface $warranty): WarrantyInterface;

    /**
     * Warranty by ID
     *
     * @param int $warrantyId
     * @return \TechNest\Warranty\Api\Data\WarrantyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $warrantyId): WarrantyInterface;

    /**
     * Warranty by serial number (case and surrounding spaces ignored)
     *
     * @param string $serial
     * @return \TechNest\Warranty\Api\Data\WarrantyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getBySerial(string $serial): WarrantyInterface;

    /**
     * Warranties matching the criteria
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \TechNest\Warranty\Api\Data\WarrantySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): WarrantySearchResultsInterface;

    /**
     * Delete a warranty
     *
     * @param \TechNest\Warranty\Api\Data\WarrantyInterface $warranty
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(WarrantyInterface $warranty): bool;

    /**
     * Delete a warranty by ID
     *
     * @param int $warrantyId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $warrantyId): bool;
}
