<?php

declare(strict_types=1);

namespace PhpFlow\Console\Command;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\ExtractSubgraph;
use PhpFlow\Application\ScanProject;
use PhpFlow\Console\ExportRouteOptions;
use PhpFlow\Exporter\JsonExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'export:json',
    description: 'Exports the application flow graph using the versioned PHPFlow JSON schema.',
)]
final class ExportJsonCommand extends Command
{
    public function __construct(
        private readonly ScanProject $scanProject,
        private readonly AnalyzeProject $analyzer,
        private readonly BuildFlowGraph $graphBuilder,
        private readonly ExtractSubgraph $subgraphExtractor,
        private readonly JsonExporter $exporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path of the PHP project to scan.')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write JSON to this file instead of stdout.')
            ->addOption('route', null, InputOption::VALUE_REQUIRED, 'Export only the flow reachable from this route path.')
            ->addOption('method', null, InputOption::VALUE_REQUIRED, 'HTTP method used with --route.', 'GET')
            ->addOption('max-depth', null, InputOption::VALUE_REQUIRED, 'Maximum traversal depth for a route subgraph.', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $routeOptions = ExportRouteOptions::from(
                $input->getOption('route'),
                $input->getOption('method'),
                $input->getOption('max-depth'),
            );
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $project = $this->scanProject->scan((string) $input->getArgument('path'));
        $analysis = $this->analyzer->analyze($project);
        $graph = $this->graphBuilder->build($analysis);

        if ($routeOptions->startNodeId() !== null) {
            $subgraph = $this->subgraphExtractor->from(
                $graph,
                $routeOptions->startNodeId(),
                $routeOptions->maxDepth(),
            );

            if ($subgraph === null) {
                $io->error(
                    sprintf(
                        'Route "%s %s" was not found in the graph.',
                        $routeOptions->method(),
                        $routeOptions->route(),
                    ),
                );

                return Command::FAILURE;
            }

            $graph = $subgraph;
        }

        $json = $this->exporter->export($graph);
        $outputFile = $input->getOption('output');

        if (is_string($outputFile) && $outputFile !== '') {
            $directory = dirname($outputFile);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Unable to create directory "%s".', $directory));
            }

            if (file_put_contents($outputFile, $json) === false) {
                throw new \RuntimeException(sprintf('Unable to write JSON file "%s".', $outputFile));
            }

            $io->success('JSON graph generated successfully.');

            return Command::SUCCESS;
        }

        $output->write($json);

        return Command::SUCCESS;
    }
}
