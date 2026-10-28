<?php
declare(strict_types=1);

namespace TechNest\CartSelect\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\TestCase;
use TechNest\CartSelect\Model\ItemSelection;
use TechNest\CartSelect\Observer\RowTotalsOfUnselectedLines;

class RowTotalsOfUnselectedLinesTest extends TestCase
{
    public function testOnlyUntickedLinesGetTheirRowTotalRecalculated(): void
    {
        $ticked = $this->createMock(Item::class);
        $ticked->method('getData')->willReturn(1);
        $ticked->expects(self::never())->method('calcRowTotal');

        $unticked = $this->createMock(Item::class);
        $unticked->method('getData')->willReturnMap([
            [ItemSelection::FIELD, null, 0],
            ['row_total', null, 562250.0],
            ['base_row_total', null, 22.49],
        ]);
        $unticked->expects(self::once())->method('calcRowTotal')->willReturnSelf();
        $unticked->expects(self::exactly(2))->method('setData')->willReturnCallback(
            function (string $key, $value) use ($unticked) {
                self::assertSame(
                    ['row_total_incl_tax' => 562250.0, 'base_row_total_incl_tax' => 22.49][$key],
                    $value
                );
                return $unticked;
            }
        );

        $quote = $this->createStub(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([$ticked, $unticked]);
        $event = new Event(['quote' => $quote]);

        (new RowTotalsOfUnselectedLines(new ItemSelection()))->execute(new Observer(['event' => $event]));
    }
}
