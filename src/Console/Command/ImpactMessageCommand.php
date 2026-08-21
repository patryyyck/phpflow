<?php

declare(strict_types=1);

namespace PhpFlow\Console\Command;

use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\FindMessageImpact;
use PhpFlow\Application\ScanProject;
use PhpFlow\Console\ImpactPathRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'impact:message',
    description: 'Finds entry points that can dispatch a Messenger message.',
)]
final class ImpactMessageCommand extends Command
{
    public function __construct(
        private readonly ScanProject $scanProject,
        private readonly AnalyzeProject $analyzer,
        private readonly BuildFlowGraph $graphBuilder,
        private readonly FindMessageImpact $impactFinder,
        private readonly ImpactPathRenderer $renderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path of the PHP project to inspect.')
            ->addArgument(
                'message',
                InputArgument::REQUIRED,
                'Messenger message FQCN or short class name.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');
        $message = (string) $input->getArgument('message');

        $project = $this->scanProject->scan($path);
        $analysis = $this->analyzer->analyze($project);
        $graph = $this->graphBuilder->build($analysis);
        $impacts = $this->impactFinder->find($graph, $message);

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Entry points dispatching message %s', $message));

        if ($impacts === []) {
            $output->writeln('No entry point found.');

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
