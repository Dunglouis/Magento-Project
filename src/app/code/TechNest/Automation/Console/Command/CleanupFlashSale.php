<?php
declare(strict_types=1);

namespace TechNest\Automation\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\Automation\Model\FlashSaleCleanup;

/**
 * technest:flash-sale:cleanup [--dry-run]
 */
class CleanupFlashSale extends Command
{
    private const OPTION_DRY_RUN = 'dry-run';

    /**
     * @param State $appState
     * @param FlashSaleCleanup $flashSaleCleanup
     */
    public function __construct(
        private readonly State $appState,
        private readonly FlashSaleCleanup $flashSaleCleanup
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('technest:flash-sale:cleanup')
            ->setDescription('Remove special prices whose sale has ended and refresh the cached pages')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Only list the products');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException) {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        }
        $dryRun = (bool)$input->getOption(self::OPTION_DRY_RUN);
        $skus = $this->flashSaleCleanup->run($dryRun);
        $output->writeln(sprintf(
            '<info>%s %d product(s) with an ended sale</info>',
            $dryRun ? 'Would clean up' : 'Cleaned up',
            count($skus)
        ));
        foreach ($skus as $sku) {
            $output->writeln('  - ' . $sku);
        }
        return Cli::RETURN_SUCCESS;
    }
}
