<?php
declare(strict_types=1);

namespace TechNest\Warranty\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Warranty search results
 *
 * @api
 */
interface WarrantySearchResultsInterface extends SearchResultsInterface
{
    /**
     * Warranties
     *
     * @return \TechNest\Warranty\Api\Data\WarrantyInterface[]
     */
    public function getItems();

    /**
     * Set warranties
     *
     * @param \TechNest\Warranty\Api\Data\WarrantyInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
