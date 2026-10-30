<?php
declare(strict_types=1);

namespace TechNest\Warranty\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit / Delete links of a grid row (only for admins allowed to manage warranties)
 */
class Actions extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritdoc
     *
     * @param array<string,mixed> $dataSource
     * @return array<string,mixed>
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])
            || !$this->authorization->isAllowed('TechNest_Warranty::manage')
        ) {
            return $dataSource;
        }
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $id = ['warranty_id' => $item['warranty_id']];
            $item[$name] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl('technest_warranty/warranty/edit', $id),
                    'label' => __('Edit'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl('technest_warranty/warranty/delete', $id),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete %1', $item['serial']),
                        'message' => __('Are you sure you want to delete this warranty?'),
                    ],
                    'post' => true,
                ],
            ];
        }
        return $dataSource;
    }
}
