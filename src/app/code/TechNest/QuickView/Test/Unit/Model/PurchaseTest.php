<?php
declare(strict_types=1);

namespace TechNest\QuickView\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use TechNest\QuickView\Model\Purchase;

class PurchaseTest extends TestCase
{
    public function testSimpleAndVirtualInStockAreAddedDirectly(): void
    {
        $purchase = new Purchase();
        $this->assertTrue($purchase->canAddDirectly('simple', true, false));
        $this->assertTrue($purchase->canAddDirectly('virtual', true, false));
    }

    public function testOptionsOrStockSendToTheProductPage(): void
    {
        $purchase = new Purchase();
        $this->assertFalse($purchase->canAddDirectly('simple', false, false), 'out of stock');
        $this->assertFalse($purchase->canAddDirectly('simple', true, true), 'required custom options');
        $this->assertFalse($purchase->canAddDirectly('configurable', true, false));
        $this->assertFalse($purchase->canAddDirectly('bundle', true, false));
        $this->assertFalse($purchase->canAddDirectly('grouped', true, false));
    }
}
