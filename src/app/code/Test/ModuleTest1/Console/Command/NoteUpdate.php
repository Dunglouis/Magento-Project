<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Console\Command;

use Magento\Framework\Exception\NoSuchEntityException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Test\ModuleTest1\Api\NoteRepositoryInterface;

/**
 * UPDATE: bin/magento test:note:update <id> [--title=...] [--content=...] [--active=0|1]
 */
class NoteUpdate extends Command
{
    public function __construct(
        private readonly NoteRepositoryInterface $noteRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('test:note:update')
            ->setDescription('Update a note')
            ->addArgument('id', InputArgument::REQUIRED, 'Note ID')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'New title')
            ->addOption('content', null, InputOption::VALUE_REQUIRED, 'New content')
            ->addOption('active', null, InputOption::VALUE_REQUIRED, '1 = active, 0 = inactive');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Luôn load bản ghi cũ trước, rồi chỉ sửa các trường được truyền vào.
            $note = $this->noteRepository->getById((int) $input->getArgument('id'));
        } catch (NoSuchEntityException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        if (($title = $input->getOption('title')) !== null) {
            $note->setTitle($title);
        }
        if (($content = $input->getOption('content')) !== null) {
            $note->setContent($content);
        }
        if (($active = $input->getOption('active')) !== null) {
            $note->setIsActive((bool) $active);
        }

        $this->noteRepository->save($note);
        $output->writeln(sprintf('<info>Updated note #%d</info>', $note->getId()));

        return Command::SUCCESS;
    }
}
