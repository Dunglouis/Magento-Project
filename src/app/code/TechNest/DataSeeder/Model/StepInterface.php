<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * One idempotent seeding step (attributes, categories, products...).
 */
interface StepInterface
{
    /**
     * Short human-readable description shown before the step runs.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Create or update the step's data; must be safe to run repeatedly.
     *
     * @param OutputInterface $output
     * @return void
     */
    public function execute(OutputInterface $output): void;
}
