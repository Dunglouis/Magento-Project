<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model\ResourceModel\Warranty;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use TechNest\Warranty\Model\ResourceModel\Warranty as WarrantyResource;
use TechNest\Warranty\Model\Warranty;

/**
 * Warranty collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'warranty_id';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Warranty::class, WarrantyResource::class);
    }
}
