<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Console\Command;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\DataSeeder\Model\SeedData;

/**
 * Deletes catalog data that the seeder does not own:
 * products whose SKU does not start with "TN-" and categories outside the seed tree.
 * Without --force it only lists what would be deleted.
 */
class CleanupLegacy extends Command
{
    public const SKU_PREFIX = 'TN-';
    private const OPTION_FORCE = 'force';
    private const ROOT_CATEGORY_PATH = '1/2/';

    /**
     * @param State $appState
     * @param Registry $registry
     * @param SeedData $seedData
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        private readonly State $appState,
        private readonly Registry $registry,
        private readonly SeedData $seedData,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('technest:seed:cleanup-legacy')
            ->setDescription('Delete products/categories not owned by the TechNest seeder (dry run without --force)')
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Actually delete');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException) {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        }
        $force = (bool)$input->getOption(self::OPTION_FORCE);
        $this->registry->register('isSecureArea', true, true);

        $products = $this->productCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addFieldToFilter('sku', ['nlike' => self::SKU_PREFIX . '%']);
        $output->writeln(sprintf('<info>Products to delete: %d</info>', $products->getSize()));
        foreach ($products as $product) {
            $output->writeln(sprintf('  - [%d] %s (%s)', $product->getId(), $product->getSku(), $product->getName()));
            if ($force) {
                $this->productRepository->delete($product);
            }
        }

        $categories = $this->findLegacyCategories();
        $output->writeln(sprintf('<info>Categories to delete (with their children): %d</info>', count($categories)));
        foreach ($categories as $category) {
            $output->writeln(
                sprintf('  - [%d] %s (%s)', $category->getId(), $category->getName(), $category->getUrlPath())
            );
            if ($force) {
                $this->categoryRepository->delete($category);
            }
        }

        $output->writeln($force
            ? '<info>Deleted.</info> Next: bin/magento indexer:reindex && bin/magento cache:flush'
            : '<comment>Dry run. Back up the DB, then re-run with --force.</comment>');
        return Cli::RETURN_SUCCESS;
    }

    /**
     * Top-most categories outside the seed tree (deleting one also deletes its children).
     *
     * @return \Magento\Catalog\Model\Category[]
     */
    private function findLegacyCategories(): array
    {
        $seedPaths = array_flip($this->seedData->getCategoryUrlPaths());
        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect(['name', 'url_path'])
            ->addPathFilter('^' . self::ROOT_CATEGORY_PATH)
            ->setOrder('level', 'ASC');

        $legacy = [];
        $legacyIds = [];
        foreach ($collection as $category) {
            $ancestorIds = explode('/', (string)$category->getPath());
            if (array_intersect($ancestorIds, $legacyIds)) {
                continue;
            }
            if (!isset($seedPaths[(string)$category->getUrlPath()])) {
                $legacy[] = $category;
                $legacyIds[] = (string)$category->getId();
            }
        }
        return $legacy;
    }
}
