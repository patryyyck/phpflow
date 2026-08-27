<?php

declare(strict_types=1);

namespace PhpFlow\Console\Command;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\ExtractSubgraph;
use PhpFlow\Application\ScanProject;
use PhpFlow\Exporter\HtmlExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'export:html',
    description: 'Exports a self-contained interactive HTML visualization of the flow graph.',
)]
final class ExportHtmlCommand extends Command
{
    public function __construct(
        private readonly ScanProject $scanProject,
        private readonly AnalyzeProject $analyzer,
        private readonly BuildFlowGraph $graphBuilder,
        private readonly ExtractSubgraph $subgraphExtractor,
        private readonly HtmlExporter $exporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path of the PHP project to scan.')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'HTML output file.', '/tmp/phpflow.html')
            ->addOption('route', null, InputOption::VALUE_REQUIRED, 'Export only the flow reachable from this route path.')
            ->addOption('method', null, InputOption::VALUE_REQUIRED, 'HTTP method used with --route.', 'GET')
            ->addOption('max-depth', null, InputOption::VALUE_REQUIRED, 'Maximum traversal depth for a route subgraph.', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->scanProject->scan((string) $input->getArgument('path'));
        $analysis = $this->analyzer->analyze($project);
        $graph = $this->graphBuilder->build($analysis);
        $route = $input->getOption('route');

        if (is_string($route) && $route !== '') {
            $method = strtoupper((string) $input->getOption('method'));
            $subgraph = $this->subgraphExtractor->from(
                $graph,
                sprintf('route:%s:%s', $method, $route),
                (int) $input->getOption('max-depth'),
            );

            if ($subgraph === null) {
                (new SymfonyStyle($input, $output))->error(
                    sprintf('Route "%s %s" was not found in the graph.', $method, $route),
                );

                return Command::FAILURE;
            }

            $graph = $subgraph;
        }

        $outputFile = (string) $input->getOption('output');
        $directory = dirname($outputFile);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s".', $directory));
        }

        if (file_put_contents($outputFile, $this->exporter->export($graph)) === false) {
            throw new \RuntimeException(sprintf('Unable to write HTML file "%s".', $outputFile));
        }

        (new SymfonyStyle($input, $output))->success('Interactive HTML graph generated successfully.');

        return Command::SUCCESS;
    }
}
