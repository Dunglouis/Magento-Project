<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * bin/magento technest:seed:run [--step=a,b]
 */
class SeedRun extends Command
{
    private const OPTION_STEP = 'step';

    /**
     * @param State $appState
     * @param StepInterface[] $steps Keyed by step code, in run order (see di.xml)
     */
    public function __construct(
        private readonly State $appState,
        private readonly array $steps = []
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('technest:seed:run')
            ->setDescription('Seed the TechNest catalog. Safe to run repeatedly.')
            ->addOption(
                self::OPTION_STEP,
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated steps to run (default: all): ' . implode(', ', array_keys($this->steps))
            );
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

        foreach ($this->selectSteps((string)$input->getOption(self::OPTION_STEP)) as $code => $step) {
            $output->writeln(sprintf('<info>== %s</info>: %s', $code, $step->getDescription()));
            $step->execute($output);
        }

        $output->writeln('<info>Done.</info> Next: bin/magento indexer:reindex && bin/magento cache:flush');
        return Cli::RETURN_SUCCESS;
    }

    /**
     * Steps to run, keeping the configured order.
     *
     * @param string $option
     * @return StepInterface[]
     * @throws LocalizedException
     */
    private function selectSteps(string $option): array
    {
        if ($option === '') {
            return $this->steps;
        }
        $codes = array_map('trim', explode(',', $option));
        $unknown = array_diff($codes, array_keys($this->steps));
        if ($unknown) {
            throw new LocalizedException(__('Unknown step(s): %1', implode(', ', $unknown)));
        }
        return array_intersect_key($this->steps, array_flip($codes));
    }
}
