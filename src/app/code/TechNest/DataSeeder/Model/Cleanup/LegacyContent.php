<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Cleanup;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use Magento\Widget\Model\ResourceModel\Widget\Instance as WidgetInstanceResource;
use Magento\Widget\Model\ResourceModel\Widget\Instance\CollectionFactory as WidgetCollectionFactory;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Luma sample data leftovers: CMS blocks, the widgets that show them, sample cart rules and media files.
 * Lists everything; deletes only when $force is true.
 */
class LegacyContent
{
    public const BLOCK_PREFIX = 'technest-';
    private const CMS_BLOCK_WIDGET = \Magento\Cms\Block\Widget\Block::class;
    private const SAMPLE_SALES_RULES = [
        'Buy 3 tee shirts and get the 4th free',
        'Spend $50 or more - shipping is free!',
        '20% OFF Ever $200-plus purchase!*',
        '$4 Luma water bottle (save 70%)',
    ];
    private const SAMPLE_MEDIA = [
        'styles.css',
        'wysiwyg/collection', 'wysiwyg/gear', 'wysiwyg/giftcards', 'wysiwyg/home', 'wysiwyg/mens',
        'wysiwyg/new', 'wysiwyg/sale', 'wysiwyg/training', 'wysiwyg/womens',
    ];

    /**
     * @param BlockCollectionFactory $blockCollectionFactory
     * @param BlockRepositoryInterface $blockRepository
     * @param WidgetCollectionFactory $widgetCollectionFactory
     * @param WidgetInstanceResource $widgetInstanceResource
     * @param RuleCollectionFactory $ruleCollectionFactory
     * @param RuleRepositoryInterface $ruleRepository
     * @param Filesystem $filesystem
     * @param Json $json
     */
    public function __construct(
        private readonly BlockCollectionFactory $blockCollectionFactory,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly WidgetCollectionFactory $widgetCollectionFactory,
        private readonly WidgetInstanceResource $widgetInstanceResource,
        private readonly RuleCollectionFactory $ruleCollectionFactory,
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly Filesystem $filesystem,
        private readonly Json $json
    ) {
    }

    /**
     * List (and with $force delete) legacy content
     *
     * @param OutputInterface $output
     * @param bool $force
     * @return void
     */
    public function run(OutputInterface $output, bool $force): void
    {
        $blocks = $this->blockCollectionFactory->create()
            ->addFieldToFilter('identifier', ['nlike' => self::BLOCK_PREFIX . '%']);
        $legacyBlockIds = array_map('strval', $blocks->getAllIds());

        $widgets = [];
        foreach ($this->widgetCollectionFactory->create() as $widget) {
            $params = $this->json->unserialize((string)$widget->getData('widget_parameters') ?: '{}');
            if ($widget->getData('instance_type') === self::CMS_BLOCK_WIDGET
                && in_array((string)($params['block_id'] ?? ''), $legacyBlockIds, true)
            ) {
                $widgets[] = $widget;
            }
        }
        $output->writeln(sprintf('<info>Widgets to delete: %d</info>', count($widgets)));
        foreach ($widgets as $widget) {
            $output->writeln(sprintf('  - [%d] %s', $widget->getId(), $widget->getData('title')));
            if ($force) {
                $this->widgetInstanceResource->delete($widget);
            }
        }

        $output->writeln(sprintf('<info>CMS blocks to delete: %d</info>', $blocks->getSize()));
        foreach ($blocks as $block) {
            $output->writeln(sprintf('  - [%d] %s', $block->getId(), $block->getIdentifier()));
            if ($force) {
                $this->blockRepository->delete($block);
            }
        }

        $rules = $this->ruleCollectionFactory->create()->addFieldToFilter('name', ['in' => self::SAMPLE_SALES_RULES]);
        $output->writeln(sprintf('<info>Sample cart price rules to delete: %d</info>', $rules->getSize()));
        foreach ($rules as $rule) {
            $output->writeln(sprintf('  - [%d] %s', $rule->getId(), $rule->getName()));
            if ($force) {
                $this->ruleRepository->deleteById((int)$rule->getId());
            }
        }

        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $paths = array_values(array_filter(self::SAMPLE_MEDIA, [$media, 'isExist']));
        $output->writeln(sprintf('<info>Sample media paths to delete: %d</info>', count($paths)));
        foreach ($paths as $path) {
            $output->writeln('  - pub/media/' . $path);
            if ($force) {
                $media->delete($path);
            }
        }
    }
}
