<?php

declare(strict_types=1);

namespace PhpFlow\Console\Command;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\FindTableImpact;
use PhpFlow\Application\ScanProject;
use PhpFlow\Console\ImpactPathRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'impact:table',
    description: 'Finds routes that can reach a database table.',
)]
final class ImpactTableCommand extends Command
{
    public function __construct(
        private readonly ScanProject $scanProject,
        private readonly AnalyzeProject $analyzer,
        private readonly BuildFlowGraph $graphBuilder,
        private readonly FindTableImpact $impactFinder,
        private readonly ImpactPathRenderer $renderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path of the PHP project to inspect.')
            ->addArgument('table', InputArgument::REQUIRED, 'Database table name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');
        $table = (string) $input->getArgument('table');

        $project = $this->scanProject->scan($path);
        $analysis = $this->analyzer->analyze($project);
        $graph = $this->graphBuilder->build($analysis);
        $impacts = $this->impactFinder->find($graph, $table);

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Routes impacting table %s', $table));

        if ($impacts === []) {
            $output->writeln('No route found.');

            return Command::SUCCESS;
        }

        foreach ($impacts as $index => $impact) {
            if ($index > 0) {
                $output->writeln('');
            }

            foreach ($this->renderer->render($impact) as $line) {
                $output->writeln($line);
            }
        }

        return Command::SUCCESS;
    }
}
