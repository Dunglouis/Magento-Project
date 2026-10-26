<?php
declare(strict_types=1);

namespace TechNest\CustomerAccess\Setup\Patch\Data;

use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Attribute as AttributeResource;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Customer attribute "phone_number": shown on register/edit forms and in admin (form + grid).
 * Optional at EAV level so existing customers stay valid; the storefront forms require it.
 */
class AddPhoneNumberAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'phone_number';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     * @param AttributeResource $attributeResource
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly AttributeSetFactory $attributeSetFactory,
        private readonly AttributeResource $attributeResource
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerSetup->addAttribute(Customer::ENTITY, self::ATTRIBUTE_CODE, [
            'type' => 'varchar',
            'label' => 'Phone Number',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'user_defined' => true,
            'system' => false,
            'position' => 85,
            'sort_order' => 85,
            'is_used_in_grid' => true,
            'is_visible_in_grid' => true,
            'is_filterable_in_grid' => true,
            'is_searchable_in_grid' => true,
        ]);

        $attributeSetId = (int)$customerSetup->getEavConfig()
            ->getEntityType(Customer::ENTITY)
            ->getDefaultAttributeSetId();
        $attributeGroupId = (int)$this->attributeSetFactory->create()->getDefaultGroupId($attributeSetId);

        $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, self::ATTRIBUTE_CODE);
        $attribute->addData([
            'attribute_set_id' => CustomerMetadataInterface::ATTRIBUTE_SET_ID_CUSTOMER,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => ['adminhtml_customer', 'customer_account_create', 'customer_account_edit'],
        ]);
        $this->attributeResource->save($attribute);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
