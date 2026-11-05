<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\Base\Setup\Patch\Data\CreateStoreViews;
use TechNest\DataSeeder\Model\ConfigurableLinker;
use TechNest\DataSeeder\Model\DummyJson\Client as DummyJsonClient;
use TechNest\DataSeeder\Model\Icecat\Client as IcecatClient;
use TechNest\DataSeeder\Model\ImageDownloader;
use TechNest\DataSeeder\Model\ProductDataMapper;
use TechNest\DataSeeder\Model\ProductImageAttacher;
use TechNest\DataSeeder\Model\ProductWriter;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * Simple, virtual and configurable products from data/products/*.json, enriched with DummyJSON (dummyjson_id)
 * or Open Icecat (icecat). Configurable products get one simple child per option combination.
 * Bundle and grouped products are handled by the composites step.
 */
class Products implements StepInterface
{
    /**
     * @param SeedData $seedData
     * @param DummyJsonClient $dummyJson
     * @param IcecatClient $icecat
     * @param ProductDataMapper $mapper
     * @param ProductWriter $writer
     * @param ImageDownloader $images
     * @param ProductImageAttacher $imageAttacher
     * @param ConfigurableLinker $configurableLinker
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly SeedData $seedData,
        private readonly DummyJsonClient $dummyJson,
        private readonly IcecatClient $icecat,
        private readonly ProductDataMapper $mapper,
        private readonly ProductWriter $writer,
        private readonly ImageDownloader $images,
        private readonly ProductImageAttacher $imageAttacher,
        private readonly ConfigurableLinker $configurableLinker,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Simple, virtual and configurable products with images (seed data + DummyJSON + Open Icecat)';
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
            if (!in_array($type, ['simple', 'virtual', 'configurable'], true)) {
                continue;
            }
            $api = isset($seed['dummyjson_id']) ? $this->dummyJson->getProduct((int)$seed['dummyjson_id']) : [];
            $icecat = [];
            if (isset($seed['icecat'])) {
                try {
                    $icecat = $this->icecat->getProduct($seed['icecat']['brand'], $seed['icecat']['mpn']);
                } catch (LocalizedException $e) {
                    $output->writeln('<comment>  ! ' . $e->getMessage() . ' (saved without Icecat content)</comment>');
                }
            }
            $data = $this->mapper->map($seed, $api, $icecat);
            $variants = $type === 'configurable' ? $this->mapper->mapVariants($data, $seed) : [];
            foreach ($variants as $variant) {
                $this->writer->save($variant, $viStoreId);
            }
            $created = $this->writer->save($data, $viStoreId);
            if ($variants !== []) {
                $this->configurableLinker->link($data['sku'], $data['configurable_attributes'], $variants);
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
                '  %s %s  %s%s%s',
                $created ? '+' : '=',
                $data['sku'],
                $data['name']['en'],
                $variants ? sprintf(' (%d variants)', count($variants)) : '',
                $added ? sprintf(' (+%d images)', $added) : ''
            ));
        }
    }
}
