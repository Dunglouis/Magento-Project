<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model;

use Magento\Framework\Api\SearchResults;
use TechNest\Warranty\Api\Data\WarrantySearchResultsInterface;

/**
 * Search results whose items are warranties (typed for the REST API and service contracts)
 */
class WarrantySearchResults extends SearchResults implements WarrantySearchResultsInterface
{
}
