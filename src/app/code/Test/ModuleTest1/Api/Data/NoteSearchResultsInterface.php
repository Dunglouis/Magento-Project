<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Kết quả trả về của getList(): danh sách Note và tổng số dòng.
 */
interface NoteSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Test\ModuleTest1\Api\Data\NoteInterface[]
     */
    public function getItems();

    /**
     * @param \Test\ModuleTest1\Api\Data\NoteInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
