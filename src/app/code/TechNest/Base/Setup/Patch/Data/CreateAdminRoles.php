<?php
declare(strict_types=1);

namespace TechNest\Base\Setup\Patch\Data;

use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use Magento\Authorization\Model\ResourceModel\Role as RoleResource;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Authorization\Model\Role;
use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Admin roles of the shop staff (CLAUDE.md 5.8). Parents of every allowed resource are listed too,
 * so the menu entries show. Running it again resets the rules of the roles to this list.
 */
class CreateAdminRoles implements DataPatchInterface
{
    public const ROLE_CATALOG_MANAGER = 'Catalog Manager';
    public const ROLE_SALES_STAFF = 'Sales Staff';
    public const ROLE_CONTENT_EDITOR = 'Content Editor';

    /**
     * role name => allowed ACL resources
     */
    public const ROLES = [
        // Products, categories, attributes and price rules
        self::ROLE_CATALOG_MANAGER => [
            'Magento_Backend::dashboard',
            'Magento_Catalog::catalog',
            'Magento_Catalog::catalog_inventory',
            'Magento_Catalog::products',
            'Magento_Catalog::update_attributes',
            'Magento_Catalog::edit_product_design',
            'Magento_Catalog::categories',
            'Magento_Catalog::edit_category_design',
            'Magento_Backend::marketing',
            'Magento_CatalogRule::promo',
            'Magento_CatalogRule::promo_catalog',
            'Magento_SalesRule::quote',
            'Magento_Backend::stores',
            'Magento_Backend::stores_attributes',
            'Magento_Catalog::attributes_attributes',
            'Magento_Catalog::sets',
        ],
        // Orders through invoice, shipment and credit memo, customers and warranties
        self::ROLE_SALES_STAFF => [
            'Magento_Backend::dashboard',
            'Magento_Sales::sales',
            'Magento_Sales::sales_operation',
            'Magento_Sales::sales_order',
            'Magento_Sales::actions',
            'Magento_Sales::create',
            'Magento_Sales::actions_view',
            'Magento_Sales::email',
            'Magento_Sales::reorder',
            'Magento_Sales::actions_edit',
            'Magento_Sales::cancel',
            'Magento_Sales::review_payment',
            'Magento_Sales::capture',
            'Magento_Sales::invoice',
            'Magento_Sales::creditmemo',
            'Magento_Sales::hold',
            'Magento_Sales::unhold',
            'Magento_Sales::ship',
            'Magento_Sales::comment',
            'Magento_Sales::emails',
            'Magento_Sales::sales_invoice',
            'Magento_Sales::shipment',
            'Magento_Sales::sales_creditmemo',
            'Magento_Sales::transactions',
            'Magento_Sales::transactions_fetch',
            'TechNest_Warranty::warranty',
            'TechNest_Warranty::manage',
            'Magento_Customer::customer',
            'Magento_Customer::manage',
        ],
        // CMS pages and blocks (home page, hero slider), widgets, media and customer reviews
        self::ROLE_CONTENT_EDITOR => [
            'Magento_Backend::dashboard',
            'Magento_Backend::content',
            'Magento_Backend::content_elements',
            'Magento_Cms::page',
            'Magento_Cms::save',
            'Magento_Cms::page_delete',
            'Magento_Cms::block',
            'Magento_Cms::media_gallery',
            'Magento_Widget::widget_instance',
            'Magento_Backend::marketing',
            'Magento_Backend::marketing_user_content',
            'Magento_Review::reviews_all',
            'Magento_Review::pending',
        ],
    ];

    /**
     * @param RoleFactory $roleFactory
     * @param RoleResource $roleResource
     * @param RoleCollectionFactory $roleCollectionFactory
     * @param RulesFactory $rulesFactory
     */
    public function __construct(
        private readonly RoleFactory $roleFactory,
        private readonly RoleResource $roleResource,
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly RulesFactory $rulesFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        foreach (self::ROLES as $name => $resources) {
            $role = $this->getRole($name);
            $this->rulesFactory->create()
                ->setRoleId((int)$role->getId())
                ->setResources($resources)
                ->saveRel();
        }

        return $this;
    }

    /**
     * Existing role group by name, or a new one
     *
     * @param string $name
     * @return Role
     */
    private function getRole(string $name): Role
    {
        /** @var Role $role */
        $role = $this->roleCollectionFactory->create()
            ->setRolesFilter()
            ->addFieldToFilter('role_name', $name)
            ->getFirstItem();
        if ($role->getId()) {
            return $role;
        }

        $role = $this->roleFactory->create();
        $role->setRoleName($name)
            ->setRoleType(RoleGroup::ROLE_TYPE)
            ->setUserType((string)UserContextInterface::USER_TYPE_ADMIN)
            ->setUserId(0)
            ->setParentId(0)
            ->setTreeLevel(1)
            ->setSortOrder(0);
        $this->roleResource->save($role);

        return $role;
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
