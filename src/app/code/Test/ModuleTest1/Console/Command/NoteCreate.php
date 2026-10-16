<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\ModuleTest1\Api\Data\NoteInterfaceFactory;
use Test\ModuleTest1\Api\NoteRepositoryInterface;

/**
 * CREATE: bin/magento test:note:create "Tiêu đề" "Nội dung"
 */
class NoteCreate extends Command
{
    public function __construct(
        private readonly NoteInterfaceFactory $noteFactory,
        private readonly NoteRepositoryInterface $noteRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('test:note:create')
            ->setDescription('Create a note')
            ->addArgument('title', InputArgument::REQUIRED, 'Title')
            ->addArgument('content', InputArgument::OPTIONAL, 'Content');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $note = $this->noteFactory->create();
        $note->setTitle((string) $input->getArgument('title'));
        $note->setContent($input->getArgument('content'));
        $note->setIsActive(true);

        $this->noteRepository->save($note);

        $output->writeln(sprintf('<info>Created note #%d</info>', $note->getId()));

        return Command::SUCCESS;
    }
}
