<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Test\Unit\Model;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\TestCase;
use TechNest\CartSelect\Model\ItemSelection;

class ItemSelectionTest extends TestCase
{
    /**
     * @var ItemSelection
     */
    private ItemSelection $selection;

    protected function setUp(): void
    {
        $this->selection = new ItemSelection();
    }

    /**
     * @param int $id
     * @param int|null $selected tn_selected value, null when never saved
     * @param Item|null $parent
     * @return Item
     */
    private function item(int $id, ?int $selected, ?Item $parent = null): Item
    {
        $item = $this->createStub(Item::class);
        $item->method('getId')->willReturn($id);
        $item->method('getData')->willReturnCallback(
            static fn ($key = '') => $key === ItemSelection::FIELD ? $selected : null
        );
        $item->method('getParentItem')->willReturn($parent);
        return $item;
    }

    public function testLinesAreTickedUnlessUnticked(): void
    {
        self::assertTrue($this->selection->isSelected($this->item(1, null)));
        self::assertTrue($this->selection->isSelected($this->item(1, 1)));
        self::assertFalse($this->selection->isSelected($this->item(1, 0)));
    }

    public function testChildItemFollowsItsLine(): void
    {
        $unticked = $this->item(1, 0);
        $ticked = $this->item(2, 1);

        self::assertFalse($this->selection->isSelected($this->item(10, 1, $unticked)));
        self::assertTrue($this->selection->isSelected($this->item(20, null, $ticked)));
    }

    public function testFilterKeepsTickedItemsAndTheirChildren(): void
    {
        $unticked = $this->item(1, 0);
        $ticked = $this->item(2, 1);
        $items = [$unticked, $this->item(10, null, $unticked), $ticked, $this->item(20, null, $ticked)];

        self::assertSame([2 => $items[2], 3 => $items[3]], $this->selection->filter($items));
    }

    public function testLineIdsAndUntickedLinesOfAQuote(): void
    {
        $lines = [$this->item(1, 1), $this->item(2, 0), $this->item(3, null)];
        $quote = $this->createStub(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn($lines);

        self::assertSame([1, 3], $this->selection->selectedLineIds($quote));
        self::assertSame([$lines[1]], $this->selection->unselectedLines($quote));
    }
}
