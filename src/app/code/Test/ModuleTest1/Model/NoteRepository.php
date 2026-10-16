<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Test\ModuleTest1\Api\Data\NoteInterface;
use Test\ModuleTest1\Api\Data\NoteSearchResultsInterface;
use Test\ModuleTest1\Api\Data\NoteSearchResultsInterfaceFactory;
use Test\ModuleTest1\Api\NoteRepositoryInterface;
use Test\ModuleTest1\Model\ResourceModel\Note as NoteResource;
use Test\ModuleTest1\Model\ResourceModel\Note\CollectionFactory;

/**
 * Repository: nơi DUY NHẤT thực hiện CRUD cho Note.
 *
 * - Ghi / xoá / đọc 1 dòng: dùng ResourceModel.
 * - Đọc nhiều dòng: dùng Collection.
 */
class NoteRepository implements NoteRepositoryInterface
{
    public function __construct(
        private readonly NoteResource $resource,
        private readonly NoteFactory $noteFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly NoteSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    public function save(NoteInterface $note): NoteInterface
    {
        try {
            /** @var Note $note */
            $this->resource->save($note);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the note: %1', $e->getMessage()), $e);
        }

        return $note;
    }

    public function getById(int $noteId): NoteInterface
    {
        $note = $this->noteFactory->create();
        $this->resource->load($note, $noteId);

        if (!$note->getId()) {
            throw new NoSuchEntityException(__('Note with id "%1" does not exist.', $noteId));
        }

        return $note;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): NoteSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        // Áp filter, sort, page size từ SearchCriteria vào Collection.
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function delete(NoteInterface $note): bool
    {
        try {
            /** @var Note $note */
            $this->resource->delete($note);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the note: %1', $e->getMessage()), $e);
        }

        return true;
    }

    public function deleteById(int $noteId): bool
    {
        return $this->delete($this->getById($noteId));
    }
}
