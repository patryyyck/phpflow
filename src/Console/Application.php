<?php

declare(strict_types=1);

namespace PhpFlow\Console;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\CompareGraphExports;
use PhpFlow\Application\ExtractSubgraph;
use PhpFlow\Application\FindTableImpact;
use PhpFlow\Application\FindHttpImpact;
use PhpFlow\Application\FindMessageImpact;
use PhpFlow\Application\FindServiceImpact;
use PhpFlow\Application\FindExceptionImpact;
use PhpFlow\Application\ScanProject;
use PhpFlow\Application\TraverseFlowGraph;
use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Console\Command\DiffCommand;
use PhpFlow\Console\Command\ExportMermaidCommand;
use PhpFlow\Console\Command\ExportJsonCommand;
use PhpFlow\Console\Command\ExportHtmlCommand;
use PhpFlow\Console\Command\InspectCommand;
use PhpFlow\Console\Command\ImpactTableCommand;
use PhpFlow\Console\Command\ImpactHttpCommand;
use PhpFlow\Console\Command\ImpactMessageCommand;
use PhpFlow\Console\Command\ImpactServiceCommand;
use PhpFlow\Console\Command\ImpactExceptionCommand;
use PhpFlow\Console\Command\ImpactCommand;
use PhpFlow\Console\Command\ScanCommand;
use PhpFlow\Console\GraphDiffRenderer;
use PhpFlow\Console\ImpactPathRenderer;
use PhpFlow\Exporter\MermaidExporter;
use PhpFlow\Exporter\JsonExporter;
use PhpFlow\Exporter\ImpactJsonExporter;
use PhpFlow\Exporter\HtmlExporter;
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


        $this->application->add(new DiffCommand(
            new CompareGraphExports(),
            new GraphDiffRenderer(),
        ));

        $this->application->add(new ExportHtmlCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new ExtractSubgraph(),
            new HtmlExporter(),
        ));

        $this->application->add(new ExportJsonCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new ExtractSubgraph(),
            new JsonExporter(),
        ));

        $this->application->add(new ExportMermaidCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new ExtractSubgraph(),
            new MermaidExporter(),
        ));

        $this->application->add(new ImpactCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new FindTableImpact(),
            new FindHttpImpact(),
            new FindMessageImpact(),
            new FindServiceImpact(),
            new FindExceptionImpact(),
            new ImpactPathRenderer(),
            new ImpactJsonExporter(),
        ));

        $this->application->add(new ImpactHttpCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new FindHttpImpact(),
            new ImpactPathRenderer(),
        ));

        $this->application->add(new ImpactMessageCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new FindMessageImpact(),
            new ImpactPathRenderer(),
        ));

        $this->application->add(new ImpactServiceCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new FindServiceImpact(),
            new ImpactPathRenderer(),
        ));

        $this->application->add(new ImpactExceptionCommand(
            new ScanProject(new DirectoryScanner()),
            new AnalyzeProject(new \PhpFlow\Ast\ProjectAstAnalyzer(), new MessengerRoutingReader()),
            new BuildFlowGraph(),
            new FindExceptionImpact(),
            new ImpactPathRenderer(),
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
