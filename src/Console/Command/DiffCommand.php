<?php

declare(strict_types=1);

namespace PhpFlow\Console\Command;

use InvalidArgumentException;
use PhpFlow\Application\CompareGraphExports;
use PhpFlow\Console\GraphDiffRenderer;
use PhpFlow\Exporter\GraphDiffJsonExporter;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'diff',
    description: 'Compares two PHPFlow graph JSON exports.',
)]
final class DiffCommand extends Command
{
    public function __construct(
        private readonly CompareGraphExports $comparator = new CompareGraphExports(),
        private readonly GraphDiffRenderer $renderer = new GraphDiffRenderer(),
        private readonly GraphDiffJsonExporter $jsonExporter = new GraphDiffJsonExporter(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('before', InputArgument::REQUIRED, 'Previous PHPFlow graph JSON file.')
            ->addArgument('after', InputArgument::REQUIRED, 'Current PHPFlow graph JSON file.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json.', 'text')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write JSON diff to this file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $beforePath = (string) $input->getArgument('before');
        $afterPath = (string) $input->getArgument('after');

        try {
            $beforeJson = $this->readFile($beforePath);
            $afterJson = $this->readFile($afterPath);
            $diff = $this->comparator->compare($beforeJson, $afterJson);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $format = strtolower((string) $input->getOption('format'));
        $outputFile = $input->getOption('output');

        if (!in_array($format, ['text', 'json'], true)) {
            $io->error('Format must be text or json.');

            return Command::INVALID;
        }

        if ($outputFile !== null && $format !== 'json') {
            $io->error('--output can only be used with --format=json.');

            return Command::INVALID;
        }

        if ($format === 'json') {
            $json = $this->jsonExporter->export($diff);

            if (is_string($outputFile) && $outputFile !== '') {
                $directory = dirname($outputFile);

                if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                    throw new RuntimeException(sprintf('Unable to create directory "%s".', $directory));
                }

                if (file_put_contents($outputFile, $json) === false) {
                    throw new RuntimeException(sprintf('Unable to write graph diff JSON file "%s".', $outputFile));
                }

                $io->success('Graph diff JSON generated successfully.');

                return Command::SUCCESS;
            }

            $output->write($json);

            return Command::SUCCESS;
        }

        foreach ($this->renderer->render($diff) as $line) {
            $output->writeln($line);
        }

        return Command::SUCCESS;
    }

    private function readFile(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(sprintf('Unable to read graph JSON file "%s".', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read graph JSON file "%s".', $path));
        }

        return $contents;
    }
}
