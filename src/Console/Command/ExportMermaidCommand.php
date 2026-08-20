<?php

declare(strict_types=1);

namespace PhpFlow\Console\Command;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\ExtractSubgraph;
use PhpFlow\Application\ScanProject;
use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Exporter\MermaidExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'export:mermaid',
    description: 'Exports the application flow graph as Mermaid.',
)]
final class ExportMermaidCommand extends Command
{
    public function __construct(
        private readonly ScanProject $scanProject,
        private readonly AnalyzeProject $analyzer,
        private readonly BuildFlowGraph $graphBuilder,
        private readonly ExtractSubgraph $subgraphExtractor,
        private readonly MermaidExporter $exporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Path of the PHP project to scan.',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Write Mermaid output to this file instead of stdout.',
            )
            ->addOption(
                'route',
                null,
                InputOption::VALUE_REQUIRED,
                'Export only the flow reachable from this route path.',
            )
            ->addOption(
                'method',
                null,
                InputOption::VALUE_REQUIRED,
                'HTTP method used with --route.',
                'GET',
            )
            ->addOption(
                'max-depth',
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum traversal depth for a route subgraph.',
                '10',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');

        $project = $this->scanProject->scan($path);
        $analysis = $this->analyzer->analyze($project);
        $graph = $this->graphBuilder->build($analysis);

        $route = $input->getOption('route');

        if (is_string($route) && $route !== '') {
            $method = strtoupper((string) $input->getOption('method'));
            $maxDepth = (int) $input->getOption('max-depth');
            $startNodeId = sprintf('route:%s:%s', $method, $route);

            $subgraph = $this->subgraphExtractor->from($graph, $startNodeId, $maxDepth);

            if ($subgraph === null) {
                (new SymfonyStyle($input, $output))->error(
                    sprintf('Route "%s %s" was not found in the graph.', $method, $route),
                );

                return Command::FAILURE;
            }

            $graph = $subgraph;
        }

        $mermaid = $this->exporter->export($graph);

        $outputFile = $input->getOption('output');

        if (is_string($outputFile) && $outputFile !== '') {
            $directory = dirname($outputFile);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Unable to create directory "%s".', $directory));
            }

            if (file_put_contents($outputFile, $mermaid) === false) {
                throw new \RuntimeException(sprintf('Unable to write Mermaid file "%s".', $outputFile));
            }

            (new SymfonyStyle($input, $output))->success(
                'Mermaid graph generated successfully.',
            );

            return Command::SUCCESS;
        }

        $output->write($mermaid);

        return Command::SUCCESS;
    }
}
