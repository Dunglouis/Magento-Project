<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Model;

use Magento\Framework\Api\SearchResults;
use Test\ModuleTest1\Api\Data\NoteSearchResultsInterface;

class NoteSearchResults extends SearchResults implements NoteSearchResultsInterface
{
}
