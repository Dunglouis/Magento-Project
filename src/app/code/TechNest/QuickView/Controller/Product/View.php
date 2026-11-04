<?php
declare(strict_types=1);

namespace TechNest\QuickView\Controller\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;
use TechNest\QuickView\ViewModel\QuickView;

/**
 * GET /quickview/product/view?id=123: the quick view modal content (HTML fragment) of a product
 */
class View implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param RawFactory $rawFactory
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param LayoutInterface $layout
     * @param QuickView $viewModel
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LayoutInterface $layout,
        private readonly QuickView $viewModel
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): Raw
    {
        $result = $this->rawFactory->create();
        $result->setHeader('Cache-Control', 'no-store', true);
        $result->setHeader('X-Robots-Tag', 'noindex', true);

        $product = $this->loadProduct((int)$this->request->getParam('id'));
        if (!$product) {
            return $result->setHttpResponseCode(404)->setContents('');
        }

        /** @var Template $block */
        $block = $this->layout->createBlock(Template::class, 'technest.quickview');
        $block->setTemplate('TechNest_QuickView::product.phtml')
            ->setData('view_model', $this->viewModel)
            ->setData('product', $product);

        return $result->setContents($block->toHtml());
    }

    /**
     * Enabled product of the current website, visible in the catalog or search
     *
     * @param int $id
     * @return Product|null
     */
    private function loadProduct(int $id): ?Product
    {
        if ($id <= 0) {
            return null;
        }
        $store = $this->storeManager->getStore();
        try {
            $product = $this->productRepository->getById($id, false, (int)$store->getId());
        } catch (NoSuchEntityException) {
            return null;
        }
        if (!$product instanceof Product
            || !$product->isVisibleInSiteVisibility()
            || (int)$product->getStatus() !== Product\Attribute\Source\Status::STATUS_ENABLED
            || !in_array((int)$store->getWebsiteId(), array_map('intval', $product->getWebsiteIds()), true)
        ) {
            return null;
        }
        return $product;
    }
}
