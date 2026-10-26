<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\DataSeeder\Model\Cms\TemplateRenderer;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * CMS pages and blocks per store view (en, vi) built from data/cms/<name>.json + templates.
 * Runs after products (templates use their images).
 */
class Content implements StepInterface
{
    private const ITEMS = ['home', 'about-us', 'customer-service', 'footer'];
    private const TYPE_BLOCK = 'block';

    /**
     * @param SeedData $seedData
     * @param TemplateRenderer $renderer
     * @param StoreManagerInterface $storeManager
     * @param PageCollectionFactory $pageCollectionFactory
     * @param PageFactory $pageFactory
     * @param PageRepositoryInterface $pageRepository
     * @param ProductRepositoryInterface $productRepository
     * @param BlockCollectionFactory $blockCollectionFactory
     * @param BlockFactory $blockFactory
     * @param BlockRepositoryInterface $blockRepository
     */
    public function __construct(
        private readonly SeedData $seedData,
        private readonly TemplateRenderer $renderer,
        private readonly StoreManagerInterface $storeManager,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly PageFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly BlockCollectionFactory $blockCollectionFactory,
        private readonly BlockFactory $blockFactory,
        private readonly BlockRepositoryInterface $blockRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'CMS pages (home, about us, customer service) and blocks (footer) per store view';
    }

    /**
     * @inheritdoc
     */
    public function execute(OutputInterface $output): void
    {
        foreach (self::ITEMS as $name) {
            $definition = $this->seedData->getCmsPage($name);
            $isBlock = ($definition['type'] ?? 'page') === self::TYPE_BLOCK;
            foreach ($definition['stores'] as $storeCode => $storeData) {
                $storeId = (int)$this->storeManager->getStore($storeCode)->getId();
                $content = $this->renderer->render(
                    $this->seedData->getCmsTemplate($storeData['template']),
                    fn (string $sku): ?string => $this->getImagePath($sku, $output)
                );

                $isNew = $isBlock
                    ? $this->saveBlock($definition, $storeData, $storeId, $content)
                    : $this->savePage($definition, $storeData, $storeId, $content);

                $output->writeln(sprintf(
                    '  %s %s "%s" [%s]',
                    $isNew ? 'created' : 'updated',
                    $isBlock ? 'block' : 'page',
                    $definition['identifier'],
                    $storeCode
                ));
            }
        }
    }

    /**
     * Create/update a CMS page for one store view; returns true when created
     *
     * @param array<string,mixed> $definition
     * @param array<string,string> $storeData
     * @param int $storeId
     * @param string $content
     * @return bool
     */
    private function savePage(array $definition, array $storeData, int $storeId, string $content): bool
    {
        $page = $this->findPage($definition['identifier'], $storeId);
        $isNew = !$page->getId();
        $page->setIdentifier($definition['identifier'])
            ->setTitle($storeData['title'])
            ->setMetaTitle($storeData['meta_title'] ?? '')
            ->setMetaDescription($storeData['meta_description'] ?? '')
            ->setContentHeading($storeData['content_heading'] ?? '')
            ->setContent($content)
            ->setPageLayout($definition['page_layout'] ?? '1column')
            ->setIsActive(true);
        $page->setData('store_id', [$storeId]);
        $this->pageRepository->save($page);
        return $isNew;
    }

    /**
     * Create/update a CMS block for one store view; returns true when created
     *
     * @param array<string,mixed> $definition
     * @param array<string,string> $storeData
     * @param int $storeId
     * @param string $content
     * @return bool
     */
    private function saveBlock(array $definition, array $storeData, int $storeId, string $content): bool
    {
        $block = $this->findBlock($definition['identifier'], $storeId);
        $isNew = !$block->getId();
        $block->setIdentifier($definition['identifier'])
            ->setTitle($storeData['title'])
            ->setContent($content)
            ->setIsActive(true);
        $block->setData('store_id', [$storeId]);
        $this->blockRepository->save($block);
        return $isNew;
    }

    /**
     * Block owned by exactly this store view, or a new one
     *
     * @param string $identifier
     * @param int $storeId
     * @return Block
     */
    private function findBlock(string $identifier, int $storeId): Block
    {
        $blocks = $this->blockCollectionFactory->create()->addFieldToFilter('identifier', $identifier);
        foreach ($blocks as $block) {
            if (array_map('intval', (array)$block->getData('store_id')) === [$storeId]) {
                return $block;
            }
        }
        return $this->blockFactory->create();
    }

    /**
     * Page already owned by this store; for the first store, take over the shared (all store views) page
     *
     * @param string $identifier
     * @param int $storeId
     * @return Page
     */
    private function findPage(string $identifier, int $storeId): Page
    {
        $shared = null;
        $pages = $this->pageCollectionFactory->create()->addFieldToFilter('identifier', $identifier);
        foreach ($pages as $page) {
            $stores = array_map('intval', (array)$page->getData('store_id'));
            if ($stores === [$storeId]) {
                return $page;
            }
            if (in_array(Store::DEFAULT_STORE_ID, $stores, true)) {
                $shared = $page;
            }
        }
        return $shared ?? $this->pageFactory->create();
    }

    /**
     * Base image path of a product (store 0), or null with a warning
     *
     * @param string $sku
     * @param OutputInterface $output
     * @return string|null
     */
    private function getImagePath(string $sku, OutputInterface $output): ?string
    {
        try {
            $product = $this->productRepository->get($sku, false, Store::DEFAULT_STORE_ID);
            $image = (string)$product->getCustomAttribute('image')?->getValue();
        } catch (NoSuchEntityException) {
            $image = '';
        }
        if ($image === '' || $image === 'no_selection') {
            $output->writeln(sprintf('  <comment>no image for %s</comment>', $sku));
            return null;
        }
        return $image;
    }
}
