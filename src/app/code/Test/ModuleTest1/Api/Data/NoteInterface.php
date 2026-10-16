<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Api\Data;

/**
 * Data Interface: "hợp đồng" mô tả một Note có những trường nào.
 * Code bên ngoài chỉ làm việc với interface này, không phụ thuộc vào Model cụ thể.
 */
interface NoteInterface
{
    public const NOTE_ID = 'note_id';
    public const TITLE = 'title';
    public const CONTENT = 'content';
    public const IS_ACTIVE = 'is_active';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id);

    public function getTitle(): ?string;

    public function setTitle(string $title): self;

    public function getContent(): ?string;

    public function setContent(?string $content): self;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): self;

    public function getCreatedAt(): ?string;

    public function getUpdatedAt(): ?string;
}
