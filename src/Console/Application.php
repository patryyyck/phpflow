<?php

declare(strict_types=1);

namespace PhpFlow\Console;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\ExtractSubgraph;
use PhpFlow\Application\FindTableImpact;
use PhpFlow\Application\ScanProject;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Console\Command\ExportMermaidCommand;
use PhpFlow\Console\Command\InspectCommand;
use PhpFlow\Console\Command\ImpactTableCommand;
use PhpFlow\Console\Command\ScanCommand;
use PhpFlow\Console\ImpactPathRenderer;
use PhpFlow\Exporter\MermaidExporter;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PhpFlow\Version;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application
{
    private readonly SymfonyApplication $application;

    public function __construct()
    {
        $this->application = new SymfonyApplication('PHPFlow', Version::VERSION);

        $this->application->add(new ScanCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
        ));


        $this->application->add(new ExportMermaidCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new ExtractSubgraph(),
            new MermaidExporter(),
        ));

        $this->application->add(new ImpactTableCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new FindTableImpact(),
            new ImpactPathRenderer(),
        ));

        $this->application->add(new InspectCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new TraverseFlowGraph(),
            new FlowTreeRenderer(),
            new FlowSummaryRenderer(),
        ));
    }

    public function run(): int
    {
        return $this->application->run();
    }
}
