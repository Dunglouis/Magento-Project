<?php
declare(strict_types=1);

namespace TechNest\Base\Observer;

use Magento\Framework\Data\Tree\Node;
use Magento\Framework\Data\Tree\NodeFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;

/**
 * Puts a "Home" item first in the top menu, linking to the home page like the logo does.
 */
class AddHomeMenuLink implements ObserverInterface
{
    public const NODE_ID = 'technest-home';

    /**
     * @param NodeFactory $nodeFactory
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly NodeFactory $nodeFactory,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        /** @var Node $menu */
        $menu = $observer->getData('menu');
        $children = $menu->getChildren();
        if (array_key_exists(self::NODE_ID, $children->getNodes())) {
            return;
        }

        $home = $this->nodeFactory->create([
            'data' => [
                'name' => __('Home'),
                'id' => self::NODE_ID,
                'url' => $this->urlBuilder->getBaseUrl(),
                'is_active' => false,
            ],
            'idField' => 'id',
            'tree' => $menu->getTree(),
        ]);

        // Node collections only append, so re-add the existing items after Home
        $existing = $children->getNodes();
        foreach ($existing as $node) {
            $children->delete($node);
        }
        $children->add($home);
        foreach ($existing as $node) {
            $children->add($node);
        }
    }
}
