<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Model;

use Magento\Framework\Model\AbstractModel;
use Test\ModuleTest1\Api\Data\NoteInterface;
use Test\ModuleTest1\Model\ResourceModel\Note as NoteResource;

/**
 * Model: đại diện cho MỘT dòng trong bảng test_note.
 */
class Note extends AbstractModel implements NoteInterface
{
    protected function _construct(): void
    {
        $this->_init(NoteResource::class);
    }

    public function getTitle(): ?string
    {
        return $this->getData(self::TITLE);
    }

    public function setTitle(string $title): NoteInterface
    {
        return $this->setData(self::TITLE, $title);
    }

    public function getContent(): ?string
    {
        return $this->getData(self::CONTENT);
    }

    public function setContent(?string $content): NoteInterface
    {
        return $this->setData(self::CONTENT, $content);
    }

    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): NoteInterface
    {
        return $this->setData(self::IS_ACTIVE, (int) $isActive);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }
}
