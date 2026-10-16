<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Model\ResourceModel\Note;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Test\ModuleTest1\Model\Note;
use Test\ModuleTest1\Model\ResourceModel\Note as NoteResource;

/**
 * Collection: lấy NHIỀU dòng của bảng test_note cùng lúc (lọc, sắp xếp, phân trang).
 */
class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Note::class, NoteResource::class);
    }
}
