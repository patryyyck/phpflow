<?php

declare(strict_types=1);

namespace PhpFlow\Console\Command;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\FindExceptionImpact;
use PhpFlow\Application\FindHttpImpact;
use PhpFlow\Application\FindMessageImpact;
use PhpFlow\Application\FindServiceImpact;
use PhpFlow\Application\FindTableImpact;
use PhpFlow\Application\ScanProject;
use PhpFlow\Console\ImpactPathRenderer;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Impact\ImpactPath;
use PhpFlow\Exporter\ImpactJsonExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'impact',
    description: 'Unified impact analysis for tables, HTTP endpoints, messages, services and exceptions.',
)]
final class ImpactCommand extends Command
{
    public function __construct(
        private readonly ScanProject $scanProject,
        private readonly AnalyzeProject $analyzer,
        private readonly BuildFlowGraph $graphBuilder,
        private readonly FindTableImpact $tableImpact,
        private readonly FindHttpImpact $httpImpact,
        private readonly FindMessageImpact $messageImpact,
        private readonly FindServiceImpact $serviceImpact,
        private readonly FindExceptionImpact $exceptionImpact,
        private readonly ImpactPathRenderer $renderer,
        private readonly ImpactJsonExporter $jsonExporter = new ImpactJsonExporter(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path of the PHP project to inspect.')
            ->addOption('table', null, InputOption::VALUE_REQUIRED, 'Database table name.')
            ->addOption('operation', null, InputOption::VALUE_REQUIRED, 'SELECT, INSERT, UPDATE or DELETE.')
            ->addOption('http', null, InputOption::VALUE_REQUIRED, 'External HTTP URL or fragment.')
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Messenger message FQCN or short name.')
            ->addOption('service', null, InputOption::VALUE_REQUIRED, 'Application class or Class::method.')
            ->addOption('exception', null, InputOption::VALUE_REQUIRED, 'Exception FQCN or short name.')
            ->addOption('summary', null, InputOption::VALUE_NONE, 'Only list unique impacted entry points.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json.', 'text')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write JSON output to this file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $criteria = $this->criteria($input);

        if (count($criteria) !== 1) {
            $io->error('Choose exactly one impact target: --table, --http, --message, --service or --exception.');

            return Command::INVALID;
        }

        [$type, $query] = $criteria[0];
        $operation = $this->operation($input);

        if ($operation !== null && $type !== 'table') {
            $io->error('--operation can only be used with --table.');

            return Command::INVALID;
        }

        if (
            $operation !== null
            && !in_array($operation, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'], true)
        ) {
            $io->error('Operation must be SELECT, INSERT, UPDATE or DELETE.');

            return Command::INVALID;
        }

        $format = strtolower((string) $input->getOption('format'));

        if (!in_array($format, ['text', 'json'], true)) {
            $io->error('Format must be text or json.');

            return Command::INVALID;
        }

        if ($input->getOption('output') !== null && $format !== 'json') {
            $io->error('--output can only be used with --format=json.');

            return Command::INVALID;
        }

        if ((bool) $input->getOption('summary') && $format === 'json') {
            $io->error('--summary cannot be combined with --format=json.');

            return Command::INVALID;
        }

        $project = $this->scanProject->scan((string) $input->getArgument('path'));
        $analysis = $this->analyzer->analyze($project);
        $graph = $this->graphBuilder->build($analysis);
        $impacts = $this->find($graph, $type, $query, $operation);

        if ($format === 'json') {
            $json = $this->jsonExporter->export(
                $graph,
                $type,
                $query,
                $impacts,
                $operation,
            );
            $outputFile = $input->getOption('output');

            if (is_string($outputFile) && $outputFile !== '') {
                $directory = dirname($outputFile);

                if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                    throw new \RuntimeException(sprintf('Unable to create directory "%s".', $directory));
                }

                if (file_put_contents($outputFile, $json) === false) {
                    throw new \RuntimeException(sprintf('Unable to write impact JSON file "%s".', $outputFile));
                }

                $io->success('Impact JSON generated successfully.');

                return Command::SUCCESS;
            }

            $output->write($json);

            return Command::SUCCESS;
        }

        $io->title(sprintf('Impact analysis: %s %s', $type, $query));

        if ($impacts === []) {
            $output->writeln('No entry point found.');

            return Command::SUCCESS;
        }

        if ((bool) $input->getOption('summary')) {
            $entryPoints = [];

            foreach ($impacts as $impact) {
                $entryPoints[$impact->root()->id()] = $impact->root()->label();
            }

            foreach ($entryPoints as $label) {
                $output->writeln($label);
            }

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

    /** @return list<array{string, string}> */
    private function criteria(InputInterface $input): array
    {
        $criteria = [];

        foreach (['table', 'http', 'message', 'service', 'exception'] as $type) {
            $value = $input->getOption($type);

            if (is_string($value) && trim($value) !== '') {
                $criteria[] = [$type, trim($value)];
            }
        }

        return $criteria;
    }

    private function operation(InputInterface $input): ?string
    {
        $operation = $input->getOption('operation');

        return is_string($operation) && trim($operation) !== ''
            ? strtoupper(trim($operation))
            : null;
    }

    /** @return list<ImpactPath> */
    private function find(
        Graph $graph,
        string $type,
        string $query,
        ?string $operation,
    ): array {
        return match ($type) {
            'table' => $this->tableImpact->find($graph, $query, $operation),
            'http' => $this->httpImpact->find($graph, $query),
            'message' => $this->messageImpact->find($graph, $query),
            'service' => $this->serviceImpact->find($graph, $query),
            'exception' => $this->exceptionImpact->find($graph, $query),
        };
    }
}
