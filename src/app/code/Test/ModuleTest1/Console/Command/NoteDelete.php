<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\ModuleTest1\Api\NoteRepositoryInterface;

/**
 * DELETE: bin/magento test:note:delete <id>
 */
class NoteDelete extends Command
{
    public function __construct(
        private readonly NoteRepositoryInterface $noteRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('test:note:delete')
            ->setDescription('Delete a note')
            ->addArgument('id', InputArgument::REQUIRED, 'Note ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (int) $input->getArgument('id');

        try {
            $this->noteRepository->deleteById($id);
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Deleted note #%d</info>', $id));

        return Command::SUCCESS;
    }
}
