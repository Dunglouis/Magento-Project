<?php
declare(strict_types=1);

namespace TechNest\Warranty\Model\Warranty;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use TechNest\Warranty\Controller\Adminhtml\Warranty\Save;
use TechNest\Warranty\Model\Config;
use TechNest\Warranty\Model\ResourceModel\Warranty\CollectionFactory;

/**
 * Data of the warranty form; a new warranty starts today with the default cover length
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var array<int|string,array<string,mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param Config $config
     * @param TimezoneInterface $timezone
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly Config $config,
        private readonly TimezoneInterface $timezone,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @inheritdoc
     *
     * @return array<int|string,array<string,mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        foreach ($this->collection->getItems() as $warranty) {
            $this->loadedData[$warranty->getId()] = $warranty->getData();
        }

        $persisted = $this->dataPersistor->get(Save::PERSIST_KEY);
        if (is_array($persisted) && $persisted) {
            $this->loadedData[$persisted['warranty_id'] ?? ''] = $persisted;
            $this->dataPersistor->clear(Save::PERSIST_KEY);
        } elseif (!$this->loadedData) {
            $this->loadedData[''] = [
                'starts_at' => $this->timezone->date()->format('Y-m-d'),
                'months' => $this->config->getDefaultMonths(),
                'status' => 'active',
            ];
        }
        return $this->loadedData;
    }
}
