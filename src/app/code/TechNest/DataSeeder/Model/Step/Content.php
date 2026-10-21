<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
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
 * Home page per store view (en, vi) built from data/cms templates. Runs after products (uses their images).
 */
class Content implements StepInterface
{
    private const PAGES = ['home'];

    /**
     * @param SeedData $seedData
     * @param TemplateRenderer $renderer
     * @param StoreManagerInterface $storeManager
     * @param PageCollectionFactory $pageCollectionFactory
     * @param PageFactory $pageFactory
     * @param PageRepositoryInterface $pageRepository
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        private readonly SeedData $seedData,
        private readonly TemplateRenderer $renderer,
        private readonly StoreManagerInterface $storeManager,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly PageFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'CMS pages (home) per store view';
    }

    /**
     * @inheritdoc
     */
    public function execute(OutputInterface $output): void
    {
        foreach (self::PAGES as $name) {
            $definition = $this->seedData->getCmsPage($name);
            foreach ($definition['stores'] as $storeCode => $storeData) {
                $storeId = (int)$this->storeManager->getStore($storeCode)->getId();
                $page = $this->findPage($definition['identifier'], $storeId);
                $isNew = !$page->getId();

                $content = $this->renderer->render(
                    $this->seedData->getCmsTemplate($storeData['template']),
                    fn (string $sku): ?string => $this->getImagePath($sku, $output)
                );
                $page->setIdentifier($definition['identifier'])
                    ->setTitle($storeData['title'])
                    ->setMetaTitle($storeData['meta_title'])
                    ->setMetaDescription($storeData['meta_description'])
                    ->setContentHeading('')
                    ->setContent($content)
                    ->setPageLayout($definition['page_layout'])
                    ->setIsActive(true);
                $page->setData('store_id', [$storeId]);
                $this->pageRepository->save($page);

                $output->writeln(sprintf('  %s page "%s" [%s]', $isNew ? 'created' : 'updated', $name, $storeCode));
            }
        }
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
