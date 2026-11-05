<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\Base\Setup\Patch\Data\CreateStoreViews;
use TechNest\DataSeeder\Model\CompositeLinker;
use TechNest\DataSeeder\Model\Icecat\Client as IcecatClient;
use TechNest\DataSeeder\Model\ImageDownloader;
use TechNest\DataSeeder\Model\ProductDataMapper;
use TechNest\DataSeeder\Model\ProductImageAttacher;
use TechNest\DataSeeder\Model\ProductWriter;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * Bundle and grouped products from data/products/*.json; their items must already exist (products step).
 */
class Composites implements StepInterface
{
    /**
     * @param SeedData $seedData
     * @param IcecatClient $icecat
     * @param ProductDataMapper $mapper
     * @param ProductWriter $writer
     * @param CompositeLinker $linker
     * @param ImageDownloader $images
     * @param ProductImageAttacher $imageAttacher
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly SeedData $seedData,
        private readonly IcecatClient $icecat,
        private readonly ProductDataMapper $mapper,
        private readonly ProductWriter $writer,
        private readonly CompositeLinker $linker,
        private readonly ImageDownloader $images,
        private readonly ProductImageAttacher $imageAttacher,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Bundle and grouped products (combos of seeded products)';
    }

    /**
     * @inheritdoc
     */
    public function execute(OutputInterface $output): void
    {
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);
        $viStoreId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();

        foreach ($this->seedData->getProducts() as $seed) {
            $type = $seed['type'] ?? 'simple';
            if (!in_array($type, ProductDataMapper::COMPOSITE_TYPES, true)) {
                continue;
            }
            $icecat = [];
            if (isset($seed['icecat'])) {
                try {
                    $icecat = $this->icecat->getProduct($seed['icecat']['brand'], $seed['icecat']['mpn']);
                } catch (LocalizedException $e) {
                    $output->writeln('<comment>  ! ' . $e->getMessage() . ' (saved without Icecat content)</comment>');
                }
            }
            $data = $this->mapper->map($seed, [], $icecat);
            $created = $this->writer->save($data, $viStoreId);
            if ($type === 'bundle') {
                $this->linker->linkBundle($data['sku'], $data['bundle']);
                $items = count($data['bundle']);
            } else {
                $this->linker->linkGrouped($data['sku'], $data['grouped']);
                $items = count($data['grouped']);
            }
            try {
                $added = $this->imageAttacher->attach(
                    $data['sku'],
                    $this->images->download($data['url_key'], $data['images'])
                );
            } catch (LocalizedException $e) {
                $added = 0;
                $output->writeln(
                    '<comment>  ! ' . $e->getMessage() . ' (images will be retried on the next run)</comment>'
                );
            }
            $output->writeln(sprintf(
                '  %s %s  %s (%s, %d %s)%s',
                $created ? '+' : '=',
                $data['sku'],
                $data['name']['en'],
                $type,
                $items,
                $type === 'bundle' ? 'options' : 'items',
                $added ? sprintf(' (+%d images)', $added) : ''
            ));
        }
    }
}
