<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * ResourceModel: file "liên kết" giữa Model và bảng database.
 * Chỉ class này biết tên bảng và khoá chính, và là nơi chạy câu lệnh SQL.
 */
class Note extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('test_note', 'note_id');
    }
}
