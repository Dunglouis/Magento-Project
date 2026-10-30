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
use TechNest\Automation\Model\Config;
use TechNest\Automation\Model\StaleTransfers;

/**
 * technest:orders:cancel-stale [--days=N] [--dry-run]
 */
class CancelStaleTransfers extends Command
{
    private const OPTION_DAYS = 'days';
    private const OPTION_DRY_RUN = 'dry-run';

    /**
     * @param State $appState
     * @param Config $config
     * @param StaleTransfers $staleTransfers
     */
    public function __construct(
        private readonly State $appState,
        private readonly Config $config,
        private readonly StaleTransfers $staleTransfers
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('technest:orders:cancel-stale')
            ->setDescription('Cancel bank transfer orders still awaiting the transfer after the waiting time')
            ->addOption(self::OPTION_DAYS, null, InputOption::VALUE_REQUIRED, 'Waiting time in days (default: config)')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Only list the orders');
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
        $days = $input->getOption(self::OPTION_DAYS) !== null
            ? max(1, (int)$input->getOption(self::OPTION_DAYS))
            : $this->config->getTransferDays();
        $dryRun = (bool)$input->getOption(self::OPTION_DRY_RUN);

        $orders = $this->staleTransfers->cancel($days, $dryRun);
        $output->writeln(sprintf(
            '<info>%s %d order(s) awaiting transfer for more than %d day(s)</info>',
            $dryRun ? 'Would cancel' : 'Cancelled',
            count($orders),
            $days
        ));
        foreach ($orders as $incrementId) {
            $output->writeln('  - #' . $incrementId);
        }
        return Cli::RETURN_SUCCESS;
    }
}
