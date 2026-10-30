<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use TechNest\Warranty\Api\Data\WarrantyInterface;
use TechNest\Warranty\Api\Data\WarrantySearchResultsInterface;
use TechNest\Warranty\Api\Data\WarrantySearchResultsInterfaceFactory;
use TechNest\Warranty\Api\WarrantyRepositoryInterface;
use TechNest\Warranty\Model\ResourceModel\Warranty as WarrantyResource;
use TechNest\Warranty\Model\ResourceModel\Warranty\CollectionFactory;

/**
 * Warranty repository: fills the serial and the end date when missing and checks them before saving
 */
class WarrantyRepository implements WarrantyRepositoryInterface
{
    /**
     * @param WarrantyResource $resource
     * @param WarrantyFactory $warrantyFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param WarrantySearchResultsInterfaceFactory $searchResultsFactory
     * @param SerialNumber $serialNumber
     * @param CoverPeriod $coverPeriod
     */
    public function __construct(
        private readonly WarrantyResource $resource,
        private readonly WarrantyFactory $warrantyFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly WarrantySearchResultsInterfaceFactory $searchResultsFactory,
        private readonly SerialNumber $serialNumber,
        private readonly CoverPeriod $coverPeriod
    ) {
    }

    /**
     * @inheritdoc
     */
    public function save(WarrantyInterface $warranty): WarrantyInterface
    {
        /** @var Warranty $warranty */
        $serial = $this->serialNumber->normalize($warranty->getSerial());
        if ($serial === '') {
            $serial = $this->newSerial($warranty->getStartsAt());
        } elseif (!$this->serialNumber->isValid($serial)) {
            throw new CouldNotSaveException(__('"%1" is not a valid serial number (format TN-YYMM-XXXXXX).', $serial));
        }
        $warranty->setSerial($serial);
        if ($warranty->getMonths() <= 0) {
            throw new CouldNotSaveException(__('The cover length must be at least one month.'));
        }
        if ($warranty->getExpiresAt() === '') {
            $warranty->setExpiresAt($this->coverPeriod->lastDay($warranty->getStartsAt(), $warranty->getMonths()));
        }
        if ($warranty->getExpiresAt() < $warranty->getStartsAt()) {
            throw new CouldNotSaveException(__('The end date cannot be before the start date.'));
        }

        try {
            $this->resource->save($warranty);
        } catch (\Magento\Framework\Exception\AlreadyExistsException) {
            throw new CouldNotSaveException(__('Serial number %1 already exists.', $serial));
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the warranty: %1', $e->getMessage()), $e);
        }
        return $warranty;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $warrantyId): WarrantyInterface
    {
        $warranty = $this->warrantyFactory->create();
        $this->resource->load($warranty, $warrantyId);
        if (!$warranty->getId()) {
            throw new NoSuchEntityException(__('The warranty with ID "%1" does not exist.', $warrantyId));
        }
        return $warranty;
    }

    /**
     * @inheritdoc
     */
    public function getBySerial(string $serial): WarrantyInterface
    {
        $serial = $this->serialNumber->normalize($serial);
        $warranty = $this->warrantyFactory->create();
        if ($serial !== '') {
            $this->resource->load($warranty, $serial, WarrantyInterface::SERIAL);
        }
        if (!$warranty->getId()) {
            throw new NoSuchEntityException(__('No warranty was found for serial number "%1".', $serial));
        }
        return $warranty;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): WarrantySearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        /** @var WarrantyInterface[] $items */
        $items = $collection->getItems();
        $results->setItems($items);
        $results->setTotalCount($collection->getSize());
        return $results;
    }

    /**
     * @inheritdoc
     */
    public function delete(WarrantyInterface $warranty): bool
    {
        try {
            /** @var Warranty $warranty */
            $this->resource->delete($warranty);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the warranty: %1', $e->getMessage()), $e);
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteById(int $warrantyId): bool
    {
        return $this->delete($this->getById($warrantyId));
    }

    /**
     * Unused serial number for a cover that starts on the given date
     *
     * @param string $startsAt Y-m-d
     * @return string
     * @throws CouldNotSaveException
     */
    private function newSerial(string $startsAt): string
    {
        for ($try = 0; $try < 10; $try++) {
            $serial = $this->serialNumber->generate($startsAt);
            if (!$this->resource->serialExists($serial)) {
                return $serial;
            }
        }
        throw new CouldNotSaveException(__('Could not generate a unique serial number.'));
    }
}
