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
        self::assertStringContainsString('"schemaVersion": "1.2"', $html);
        self::assertStringContainsString('"label": "GET /companies"', $html);
        self::assertStringContainsString('Fit graph', $html);
        self::assertStringContainsString('Node details', $html);
        self::assertStringContainsString('displayLabel', $html);
        self::assertStringContainsString('Canonical label', $html);
        self::assertStringContainsString('id="search"', $html);
        self::assertStringContainsString('Search route, class, table, URL', $html);
        self::assertStringContainsString('function searchableText(n)', $html);
        self::assertStringContainsString('incomingEdges.get(n.id)', $html);
        self::assertStringContainsString('JSON.stringify(e.context||{})', $html);
        self::assertStringContainsString('function selectSearchResult(n)', $html);
        self::assertStringContainsString('data-preset="entry"', $html);
        self::assertStringContainsString('data-preset="database"', $html);
        self::assertStringContainsString('data-preset="external_http"', $html);
        self::assertStringContainsString('data-preset="errors"', $html);
        self::assertStringContainsString('id="hide-technical"', $html);
        self::assertStringContainsString('function presetMatches(n)', $html);
        self::assertStringContainsString('function presetVisibleIds()', $html);
        self::assertStringContainsString("['database','external_http','errors'].includes(explorePreset)", $html);
        self::assertStringContainsString("explorePreset='all';hideTechnical=false", $html);
        self::assertStringContainsString("id:'arrow'", $html);
        self::assertStringContainsString('edge-label', $html);
        self::assertStringContainsString('function stableNodeCompare(a,b)', $html);
        self::assertStringContainsString('function reorderLevel(nodes,neighbors,neighborOrder)', $html);
        self::assertStringContainsString('for(let sweep=0;sweep<4;sweep++)', $html);
        self::assertStringContainsString("el('path'", $html);
        self::assertStringContainsString('Focus branch', $html);
        self::assertStringContainsString('Direct only', $html);
        self::assertStringContainsString('Go to entry point', $html);
        self::assertStringContainsString('Entry path', $html);
        self::assertStringContainsString('function edgeKey(e)', $html);
        self::assertStringContainsString('function pathFromEntryPoint(id)', $html);
        self::assertStringContainsString("pathOnly=false", $html);
        self::assertStringContainsString("path-highlighted", $html);
        self::assertStringContainsString("id:'arrow-path'", $html);
        self::assertStringContainsString('selectedPath.edgeKeys.has(edgeKey(e))', $html);
        self::assertStringContainsString('pathOnly=Boolean(entryPointFor(n.id))', $html);
        self::assertStringContainsString('collapsed=new Set()', $html);
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
