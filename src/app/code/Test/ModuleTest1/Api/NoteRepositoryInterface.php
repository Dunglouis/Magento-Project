<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Test\ModuleTest1\Api\Data\NoteInterface;
use Test\ModuleTest1\Api\Data\NoteSearchResultsInterface;

/**
 * Repository Interface: 5 thao tác CRUD chuẩn trên bảng test_note.
 * Controller, Block, CLI, API... đều gọi qua interface này.
 */
interface NoteRepositoryInterface
{
    /**
     * Create + Update: chưa có ID thì INSERT, đã có ID thì UPDATE.
     *
     * @throws CouldNotSaveException
     */
    public function save(NoteInterface $note): NoteInterface;

    /**
     * Read 1 dòng.
     *
     * @throws NoSuchEntityException
     */
    public function getById(int $noteId): NoteInterface;

    /**
     * Read nhiều dòng, có lọc / sắp xếp / phân trang qua SearchCriteria.
     */
    public function getList(SearchCriteriaInterface $searchCriteria): NoteSearchResultsInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(NoteInterface $note): bool;

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $noteId): bool;
}
