<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Exporter;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Exporter\HtmlExporter;
use PHPUnit\Framework\TestCase;

final class HtmlExporterTest extends TestCase
{
    public function testItEmbedsVersionedGraphJsonInSelfContainedHtml(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node(
            'route:GET:/companies',
            NodeType::ROUTE,
            'GET /companies',
        ));

        $html = (new HtmlExporter())->export($graph);

        self::assertStringContainsString('<!doctype html>', $html);
        self::assertStringContainsString('id="phpflow-data"', $html);
        self::assertStringContainsString('"schemaVersion": "1.1"', $html);
        self::assertStringContainsString('"label": "GET /companies"', $html);
        self::assertStringContainsString('Fit graph', $html);
        self::assertStringContainsString('Node details', $html);
        self::assertStringNotContainsString('<script src=', $html);
        self::assertStringNotContainsString('<link rel="stylesheet" href=', $html);
        self::assertStringNotContainsString('src="https://', $html);
        self::assertStringNotContainsString('href="https://', $html);
    }

    public function testItEscapesClosingScriptSequenceInsideEmbeddedJson(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node(
            'service:unsafe',
            NodeType::SERVICE,
            'App\\Service::</script>',
        ));

        $html = (new HtmlExporter())->export($graph);

        self::assertStringContainsString('<\\/script>', $html);
    }
}
