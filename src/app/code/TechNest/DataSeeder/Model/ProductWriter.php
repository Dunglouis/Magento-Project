<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as SetCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Creates or updates one product by SKU from ProductDataMapper output.
 */
class ProductWriter
{
    private const TAX_CLASS_TAXABLE_GOODS = 2;

    /**
     * @var array<string, int>
     */
    private array $setIds = [];

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ProductInterfaceFactory $productFactory
     * @param ProductResource $productResource
     * @param CategoryLinkManagementInterface $categoryLinkManagement
     * @param SetCollectionFactory $setCollectionFactory
     * @param EavConfig $eavConfig
     * @param StoreManagerInterface $storeManager
     * @param AttributeOptionResolver $optionResolver
     * @param CategoryResolver $categoryResolver
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductInterfaceFactory $productFactory,
        private readonly ProductResource $productResource,
        private readonly CategoryLinkManagementInterface $categoryLinkManagement,
        private readonly SetCollectionFactory $setCollectionFactory,
        private readonly EavConfig $eavConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly AttributeOptionResolver $optionResolver,
        private readonly CategoryResolver $categoryResolver
    ) {
    }

    /**
     * Save the product on the default scope, then its vi store-view values.
     *
     * @param array<string,mixed> $data
     * @param int $viStoreId
     * @return bool True when the product was created, false when an existing one was updated
     * @throws LocalizedException
     */
    public function save(array $data, int $viStoreId): bool
    {
        $isNew = false;
        try {
            /** @var Product $product */
            $product = $this->productRepository->get($data['sku'], true, Store::DEFAULT_STORE_ID, true);
        } catch (NoSuchEntityException) {
            /** @var Product $product */
            $product = $this->productFactory->create();
            $isNew = true;
        }

        $product->setSku($data['sku'])
            ->setTypeId($data['type'])
            ->setAttributeSetId($this->getAttributeSetId($data['set']))
            ->setName($data['name']['en'])
            ->setDescription($data['description']['en'])
            ->setUrlKey($data['url_key'])
            ->setPrice($data['price'])
            ->setWeight($data['weight'])
            ->setStatus(Status::STATUS_ENABLED)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setTaxClassId(self::TAX_CLASS_TAXABLE_GOODS)
            ->setWebsiteIds([(int)$this->storeManager->getDefaultStoreView()->getWebsiteId()])
            ->setStockData([
                'use_config_manage_stock' => 1,
                'qty' => $data['qty'],
                'is_in_stock' => $data['qty'] > 0 ? 1 : 0,
            ]);
        foreach ($data['attributes'] as $code => $value) {
            $isSelect = $this->eavConfig->getAttribute(Product::ENTITY, $code)->getFrontendInput() === 'select';
            $product->setData($code, $isSelect ? $this->optionResolver->getOptionId($code, (string)$value) : $value);
        }
        /** @var Product $product */
        $product = $this->productRepository->save($product);

        $this->categoryLinkManagement->assignProductToCategories(
            $data['sku'],
            array_map(fn (string $path): int => $this->categoryResolver->getId($path), $data['categories'])
        );

        // vi store view: translated name, description and text specs
        $product->setStoreId($viStoreId)
            ->setName($data['name']['vi'])
            ->setDescription($data['description']['vi']);
        $this->productResource->saveAttribute($product, 'name');
        $this->productResource->saveAttribute($product, 'description');
        foreach ($data['attributes_vi'] as $code => $value) {
            $product->setData($code, $value);
            $this->productResource->saveAttribute($product, $code);
        }

        return $isNew;
    }

    /**
     * Product attribute set id by name.
     *
     * @param string $name
     * @return int
     * @throws LocalizedException
     */
    private function getAttributeSetId(string $name): int
    {
        if (!isset($this->setIds[$name])) {
            $set = $this->setCollectionFactory->create()
                ->setEntityTypeFilter((int)$this->eavConfig->getEntityType(Product::ENTITY)->getId())
                ->addFieldToFilter('attribute_set_name', $name)
                ->getFirstItem();
            if (!$set->getId()) {
                throw new LocalizedException(__('Unknown attribute set "%1": run the attributes step first', $name));
            }
            $this->setIds[$name] = (int)$set->getId();
        }
        return $this->setIds[$name];
    }
}
