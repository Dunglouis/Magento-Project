<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

use Magento\Catalog\Api\ProductAttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Label -> option id for select attributes; adds the option when it does not exist yet.
 */
class AttributeOptionResolver
{
    /**
     * @var array<string, array<string, int>> attribute code => lowercase label => option id
     */
    private array $options = [];

    /**
     * @param ProductAttributeOptionManagementInterface $optionManagement
     * @param AttributeOptionInterfaceFactory $optionFactory
     */
    public function __construct(
        private readonly ProductAttributeOptionManagementInterface $optionManagement,
        private readonly AttributeOptionInterfaceFactory $optionFactory
    ) {
    }

    /**
     * Option id for a label, creating the option if needed.
     *
     * @param string $attributeCode
     * @param string $label
     * @return int
     * @throws LocalizedException
     */
    public function getOptionId(string $attributeCode, string $label): int
    {
        $key = mb_strtolower($label);
        if (!isset($this->options[$attributeCode])) {
            $this->load($attributeCode);
        }
        if (!isset($this->options[$attributeCode][$key])) {
            $this->optionManagement->add($attributeCode, $this->optionFactory->create()->setLabel($label));
            $this->load($attributeCode);
        }
        if (!isset($this->options[$attributeCode][$key])) {
            throw new LocalizedException(__('Could not create option "%1" for "%2"', $label, $attributeCode));
        }
        return $this->options[$attributeCode][$key];
    }

    /**
     * (Re)load all options of one attribute.
     *
     * @param string $attributeCode
     * @return void
     */
    private function load(string $attributeCode): void
    {
        $this->options[$attributeCode] = [];
        foreach ($this->optionManagement->getItems($attributeCode) as $item) {
            if ((string)$item->getValue() !== '') {
                $this->options[$attributeCode][mb_strtolower((string)$item->getLabel())] = (int)$item->getValue();
            }
        }
    }
}
