<?php

declare(strict_types=1);

namespace PhpFlow\Console\Command;

use InvalidArgumentException;
use PhpFlow\Application\CompareGraphExports;
use PhpFlow\Console\GraphDiffRenderer;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('before', InputArgument::REQUIRED, 'Previous PHPFlow graph JSON file.')
            ->addArgument('after', InputArgument::REQUIRED, 'Current PHPFlow graph JSON file.');
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
