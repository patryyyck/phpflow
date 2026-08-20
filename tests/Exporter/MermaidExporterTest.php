<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Exporter;

use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Exporter\MermaidExporter;
use PHPUnit\Framework\TestCase;

final class MermaidExporterTest extends TestCase
{
    public function testItExportsNodesAndEdges(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('route:test', NodeType::ROUTE, 'POST /users'));
        $graph->addNode(new Node('controller:test', NodeType::CONTROLLER, 'UserController::create'));
        $graph->addEdge(new Edge('route:test', 'controller:test', EdgeType::INVOKES));

        $mermaid = (new MermaidExporter())->export($graph);

        self::assertStringContainsString('flowchart TD', $mermaid);
        self::assertStringContainsString('POST /users', $mermaid);
        self::assertStringContainsString('UserController::create', $mermaid);
        self::assertStringContainsString('-->|invokes|', $mermaid);
        self::assertStringContainsString('classDef type_route', $mermaid);
        self::assertStringContainsString('classDef type_controller', $mermaid);
        self::assertStringContainsString('subgraph phpflow_legend["Legend"]', $mermaid);
        self::assertStringContainsString('🌐 Route', $mermaid);
        self::assertStringContainsString('-.->|async|', $mermaid);

        self::assertStringContainsString("'theme': 'dark'", $mermaid);
        self::assertStringContainsString('linkStyle default stroke:#cbd5e1,stroke-width:1.5px;', $mermaid);
    }

    public function testItUsesStableSafeNodeIdentifiers(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node(
            'controller:App\\Controller\\UserController::create',
            NodeType::CONTROLLER,
            'App\\Controller\\UserController::create',
        ));

        $first = (new MermaidExporter())->export($graph);
        $second = (new MermaidExporter())->export($graph);

        self::assertSame($first, $second);
        self::assertMatchesRegularExpression('/n_[a-f0-9]{12}/', $first);
    }

    public function testItUsesDifferentShapesForNodeTypes(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('route:r', NodeType::ROUTE, 'GET /users'));
        $graph->addNode(new Node('controller:c', NodeType::CONTROLLER, 'UserController::list'));
        $graph->addNode(new Node('message:m', NodeType::MESSAGE, 'ListUsers'));
        $graph->addNode(new Node('handler:h', NodeType::HANDLER, 'ListUsersHandler::__invoke'));

        $mermaid = (new MermaidExporter())->export($graph);

        self::assertStringContainsString('(["🌐 GET /users"])', $mermaid);
        self::assertStringContainsString('["▣ UserController::list"]', $mermaid);
        self::assertStringContainsString('{{"✉ ListUsers"}}', $mermaid);
        self::assertStringContainsString('[["⚙ ListUsersHandler::__invoke"]]', $mermaid);
    }

    public function testItRendersAsyncDispatchesAsDottedEdges(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('handler:h', NodeType::HANDLER, 'Handler::__invoke'));
        $graph->addNode(new Node('message:m', NodeType::MESSAGE, 'Event'));
        $graph->addEdge(new Edge(
            'handler:h',
            'message:m',
            EdgeType::DISPATCHES,
            'async: cred_event',
        ));

        $mermaid = (new MermaidExporter())->export($graph);

        self::assertStringContainsString('-.->|async: cred_event|', $mermaid);
    }

    public function testItKeepsSyncDispatchesAsSolidEdges(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('handler:h', NodeType::HANDLER, 'Handler::__invoke'));
        $graph->addNode(new Node('message:m', NodeType::MESSAGE, 'Query'));
        $graph->addEdge(new Edge(
            'handler:h',
            'message:m',
            EdgeType::DISPATCHES,
            'sync',
        ));

        $mermaid = (new MermaidExporter())->export($graph);

        self::assertStringContainsString('-->|sync|', $mermaid);
    }

    public function testItUsesShortLabelsWithoutChangingGraphIdentifiers(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node(
            'controller:Example\\Catalog\\Ui\\Controller\\CatalogController::exportRecords',
            NodeType::CONTROLLER,
            'Example\\Catalog\\Ui\\Controller\\CatalogController::exportRecords',
        ));
        $graph->addNode(new Node(
            'message:Example\\Catalog\\App\\Query\\ExportRecords',
            NodeType::MESSAGE,
            'Example\\Catalog\\App\\Query\\ExportRecords',
        ));

        $mermaid = (new MermaidExporter())->export($graph);

        self::assertStringContainsString(
            '▣ CatalogController::exportRecords',
            $mermaid,
        );
        self::assertStringContainsString(
            '✉ ExportRecords',
            $mermaid,
        );
        self::assertStringNotContainsString(
            'Example\\\\Catalog\\\\Ui\\\\Controller',
            $mermaid,
        );
    }


    public function testItMarksCycleNodesInMermaid(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('message:a', NodeType::MESSAGE, 'A'));
        $graph->addNode(new Node('handler:b', NodeType::HANDLER, 'B'));
        $graph->addEdge(new Edge('message:a', 'handler:b', EdgeType::HANDLED_BY));
        $graph->addEdge(new Edge('handler:b', 'message:a', EdgeType::DISPATCHES, 'sync'));

        $mermaid = (new MermaidExporter())->export($graph);

        self::assertStringContainsString('class n_', $mermaid);
        self::assertStringContainsString('cycle_node', $mermaid);
        self::assertStringContainsString('↻ Cycle', $mermaid);
    }

    public function testItEmitsOutgoingEdgesInSourceOrder(): void
    {
        $graph = new Graph();
        $graph->addNode(new Node('handler:h', NodeType::HANDLER, 'Handler::__invoke'));
        $graph->addNode(new Node('repository:find', NodeType::REPOSITORY, 'CompanyRepository::findRequired'));
        $graph->addNode(new Node('service:register', NodeType::SERVICE, 'ExternalClient::register'));
        $graph->addNode(new Node('repository:insert', NodeType::REPOSITORY, 'RoutingRepository::insert'));

        // Deliberately add them in detector/category order, not PHP source order.
        $graph->addEdge(new Edge('handler:h', 'repository:find', EdgeType::CALLS, 'repository', 100));
        $graph->addEdge(new Edge('handler:h', 'repository:insert', EdgeType::CALLS, 'repository', 300));
        $graph->addEdge(new Edge('handler:h', 'service:register', EdgeType::CALLS, 'calls', 200));

        $mermaid = (new MermaidExporter())->export($graph);

        $find = strpos($mermaid, '-->|repository|');
        $register = strpos($mermaid, '-->|calls|', $find + 1);
        $insert = strpos($mermaid, '-->|repository|', $find + 1);

        self::assertIsInt($find);
        self::assertIsInt($register);
        self::assertIsInt($insert);
        self::assertLessThan($register, $find);
        self::assertLessThan($insert, $register);
    }


}
