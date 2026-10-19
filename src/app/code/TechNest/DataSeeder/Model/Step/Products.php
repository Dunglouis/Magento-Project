<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\Base\Setup\Patch\Data\CreateStoreViews;
use TechNest\DataSeeder\Model\DummyJson\Client as DummyJsonClient;
use TechNest\DataSeeder\Model\Icecat\Client as IcecatClient;
use TechNest\DataSeeder\Model\ProductDataMapper;
use TechNest\DataSeeder\Model\ProductWriter;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * Simple products from data/products/*.json, enriched with DummyJSON (dummyjson_id) or Open Icecat (icecat).
 * Configurable, bundle, grouped and virtual products are handled by their own steps.
 */
class Products implements StepInterface
{
    /**
     * @param SeedData $seedData
     * @param DummyJsonClient $dummyJson
     * @param IcecatClient $icecat
     * @param ProductDataMapper $mapper
     * @param ProductWriter $writer
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly SeedData $seedData,
        private readonly DummyJsonClient $dummyJson,
        private readonly IcecatClient $icecat,
        private readonly ProductDataMapper $mapper,
        private readonly ProductWriter $writer,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Simple products (seed data + DummyJSON + Open Icecat)';
    }

    /**
     * @inheritdoc
     */
    public function execute(OutputInterface $output): void
    {
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);
        $viStoreId = (int)$this->storeManager->getStore(CreateStoreViews::STORE_VI)->getId();

        foreach ($this->seedData->getProducts() as $seed) {
            if (($seed['type'] ?? 'simple') !== 'simple') {
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
            $created = $this->writer->save($data, $viStoreId);
            $output->writeln(sprintf('  %s %s  %s', $created ? '+' : '=', $data['sku'], $data['name']['en']));
        }
    }
}
