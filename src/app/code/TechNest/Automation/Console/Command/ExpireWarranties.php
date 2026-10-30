<?php
declare(strict_types=1);

namespace TechNest\Automation\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\Automation\Model\WarrantyExpiry;

/**
 * technest:warranty:expire [--dry-run]
 */
class ExpireWarranties extends Command
{
    private const OPTION_DRY_RUN = 'dry-run';

    /**
     * @param WarrantyExpiry $warrantyExpiry
     */
    public function __construct(private readonly WarrantyExpiry $warrantyExpiry)
    {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('technest:warranty:expire')
            ->setDescription('Mark active warranties whose cover has ended as expired')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Only list the warranties');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool)$input->getOption(self::OPTION_DRY_RUN);
        $serials = $this->warrantyExpiry->run($dryRun);
        $output->writeln(sprintf(
            '<info>%s %d warranty(ies)</info>',
            $dryRun ? 'Would expire' : 'Expired',
            count($serials)
        ));
        foreach ($serials as $serial) {
            $output->writeln('  - ' . $serial);
        }
        return Cli::RETURN_SUCCESS;
    }
}
