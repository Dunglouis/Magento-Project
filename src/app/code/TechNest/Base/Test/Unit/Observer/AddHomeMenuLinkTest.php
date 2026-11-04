<?php
declare(strict_types=1);

namespace TechNest\Base\Test\Unit\Observer;

use Magento\Framework\Data\Tree;
use Magento\Framework\Data\Tree\Node;
use Magento\Framework\Data\Tree\NodeFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;
use TechNest\Base\Observer\AddHomeMenuLink;

class AddHomeMenuLinkTest extends TestCase
{
    public function testHomeIsPrependedOnceBeforeCategories(): void
    {
        $tree = new Tree();
        $menu = new Node([], 'id', $tree);
        $menu->addChild(new Node(['id' => 'category-node-54', 'name' => 'Laptops'], 'id', $tree));
        $menu->addChild(new Node(['id' => 'category-node-58', 'name' => 'Phones'], 'id', $tree));

        $nodeFactory = $this->createStub(NodeFactory::class);
        $nodeFactory->method('create')->willReturnCallback(
            static fn (array $args): Node => new Node($args['data'], $args['idField'], $args['tree'])
        );
        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getBaseUrl')->willReturn('https://technest.test/');

        $observer = new AddHomeMenuLink($nodeFactory, $urlBuilder);
        $event = new Observer(['menu' => $menu]);
        $observer->execute($event);
        $observer->execute($event);

        $ids = array_keys($menu->getChildren()->getNodes());
        self::assertSame([AddHomeMenuLink::NODE_ID, 'category-node-54', 'category-node-58'], $ids);
        $home = $menu->getChildren()->getNodes()[AddHomeMenuLink::NODE_ID];
        self::assertSame('https://technest.test/', $home->getUrl());
    }
}
