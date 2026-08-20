<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Exporter;

use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Exporter\MermaidLabelFormatter;
use PHPUnit\Framework\TestCase;

final class MermaidLabelFormatterTest extends TestCase
{
    public function testItKeepsRouteLabelsUntouched(): void
    {
        $node = new Node(
            'route:GET:/export',
            NodeType::ROUTE,
            'GET /export',
        );

        self::assertSame(
            'GET /export',
            (new MermaidLabelFormatter())->format($node),
        );
    }

    public function testItShortensControllerCallables(): void
    {
        $node = new Node(
            'controller:test',
            NodeType::CONTROLLER,
            'Example\\Catalog\\Ui\\Controller\\CatalogController::exportRecords',
        );

        self::assertSame(
            'CatalogController::exportRecords',
            (new MermaidLabelFormatter())->format($node),
        );
    }

    public function testItShortensMessages(): void
    {
        $node = new Node(
            'message:test',
            NodeType::MESSAGE,
            'Example\\Catalog\\App\\Query\\ExportRecords',
        );

        self::assertSame(
            'ExportRecords',
            (new MermaidLabelFormatter())->format($node),
        );
    }

    public function testItShortensHandlers(): void
    {
        $node = new Node(
            'handler:test',
            NodeType::HANDLER,
            'Example\\Catalog\\App\\Handler\\ExportRecordsHandler::__invoke',
        );

        self::assertSame(
            'ExportRecordsHandler::__invoke',
            (new MermaidLabelFormatter())->format($node),
        );
    }

    public function testItShortensRepositories(): void
    {
        $node = new Node(
            'repository:test',
            NodeType::REPOSITORY,
            'App\\Repository\\CompanyRepository::save',
        );

        self::assertSame(
            'CompanyRepository::save',
            (new MermaidLabelFormatter())->format($node),
        );
    }

}
