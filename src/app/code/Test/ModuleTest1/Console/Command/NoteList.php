<?php
declare(strict_types=1);

namespace Test\ModuleTest1\Console\Command;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Test\ModuleTest1\Api\Data\NoteInterface;
use Test\ModuleTest1\Api\NoteRepositoryInterface;

/**
 * READ: bin/magento test:note:list [--active] [--search=từ khoá] [--limit=10]
 */
class NoteList extends Command
{
    public function __construct(
        private readonly NoteRepositoryInterface $noteRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('test:note:list')
            ->setDescription('List notes')
            ->addOption('active', null, InputOption::VALUE_NONE, 'Only active notes')
            ->addOption('search', null, InputOption::VALUE_REQUIRED, 'Search in title')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max rows', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Lọc: tương đương WHERE trong SQL.
        if ($input->getOption('active')) {
            $this->searchCriteriaBuilder->addFilter(NoteInterface::IS_ACTIVE, 1);
        }
        if ($search = $input->getOption('search')) {
            $this->searchCriteriaBuilder->addFilter(NoteInterface::TITLE, '%' . $search . '%', 'like');
        }

        // Sắp xếp mới nhất lên đầu: ORDER BY note_id DESC.
        $sortOrder = $this->sortOrderBuilder->setField(NoteInterface::NOTE_ID)->setDescendingDirection()->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addSortOrder($sortOrder)
            ->setPageSize((int) $input->getOption('limit'))
            ->setCurrentPage(1)
            ->create();

        $result = $this->noteRepository->getList($searchCriteria);

        $table = new Table($output);
        $table->setHeaders(['ID', 'Title', 'Content', 'Active', 'Created At', 'Updated At']);
        foreach ($result->getItems() as $note) {
            $table->addRow([
                $note->getId(),
                $note->getTitle(),
                $note->getContent(),
                $note->isActive() ? 'Yes' : 'No',
                $note->getCreatedAt(),
                $note->getUpdatedAt(),
            ]);
        }
        $table->render();
        $output->writeln(sprintf('Total: %d', $result->getTotalCount()));

        return Command::SUCCESS;
    }
}
