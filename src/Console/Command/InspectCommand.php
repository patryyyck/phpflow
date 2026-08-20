<?php

declare(strict_types=1);

namespace PhpFlow\Console\Command;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\ScanProject;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Console\FlowTreeRenderer;
use PhpFlow\Console\FlowSummaryRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'inspect',
    description: 'Displays the known recursive flow for a route.',
)]
final class InspectCommand extends Command
{
    public function __construct(
        private readonly ScanProject $scanProject,
        private readonly AnalyzeProject $analyzer,
        private readonly BuildFlowGraph $graphBuilder,
        private readonly TraverseFlowGraph $traverser,
        private readonly FlowTreeRenderer $renderer,
        private readonly FlowSummaryRenderer $summaryRenderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path of the PHP project to inspect.')
            ->addArgument('route', InputArgument::REQUIRED, 'Route path, for example /users.')
            ->addArgument('method', InputArgument::OPTIONAL, 'HTTP method.', 'GET')
            ->addOption(
                'summary',
                null,
                InputOption::VALUE_NONE,
                'Display only the external effects and return values for the route.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');
        $route = (string) $input->getArgument('route');
        $method = strtoupper((string) $input->getArgument('method'));

        $project = $this->scanProject->scan($path);
        $analysis = $this->analyzer->analyze($project);
        $graph = $this->graphBuilder->build($analysis);

        $startId = sprintf('route:%s:%s', $method, $route);
        $flow = $this->traverser->from($graph, $startId);

        $io = new SymfonyStyle($input, $output);

        if ($flow === null) {
            $io->error(sprintf('Route "%s %s" was not found in the graph.', $method, $route));

            return Command::FAILURE;
        }

        if ((bool) $input->getOption('summary')) {
            $io->title(sprintf('Summary for %s %s', $method, $route));

            $lines = $this->summaryRenderer->render($flow);

            if ($lines === []) {
                $output->writeln('No summarized effects found.');
            } else {
                foreach ($lines as $line) {
                    $output->writeln($line);
                }
            }

            return Command::SUCCESS;
        }

        $io->title(sprintf('Flow for %s %s', $method, $route));

        foreach ($this->renderer->render($flow) as $line) {
            $output->writeln($line);
        }

        return Command::SUCCESS;
    }
}
