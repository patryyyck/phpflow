<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\FindServiceImpact;
use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class FindServiceImpactTest extends TestCase
{
    public function testItFindsEveryMethodOfAClassFromItsFqcn(): void
    {
        $graph = $this->graph();

        $paths = (new FindServiceImpact())->find(
            $graph,
            'App\\Service\\InvoiceGenerator',
        );

        self::assertCount(2, $paths);

        $effects = array_map(
            static fn ($path): string => $path->effect()->label(),
            $paths,
        );

        sort($effects);

        self::assertSame(
            [
                'App\\Service\\InvoiceGenerator::generate',
                'App\\Service\\InvoiceGenerator::regenerate',
            ],
            $effects,
        );
    }

    public function testItCanTargetOneMethod(): void
    {
        $paths = (new FindServiceImpact())->find(
            $this->graph(),
            'App\\Service\\InvoiceGenerator::generate',
        );

        self::assertCount(1, $paths);
        self::assertSame('POST /invoices', $paths[0]->root()->label());
        self::assertSame(
            'App\\Service\\InvoiceGenerator::generate',
            $paths[0]->effect()->label(),
        );
    }

    public function testItAcceptsShortClassAndShortCallableNames(): void
    {
        self::assertCount(
            2,
            (new FindServiceImpact())->find(
                $this->graph(),
                'InvoiceGenerator',
            ),
        );

        $paths = (new FindServiceImpact())->find(
            $this->graph(),
            'InvoiceGenerator::regenerate',
        );

        self::assertCount(1, $paths);
        self::assertSame(
            'App\\Message\\RegenerateInvoice',
            $paths[0]->root()->label(),
        );
    }

    public function testItIncludesRepositoriesAndHandlers(): void
    {
        $graph = $this->graph();

        self::assertCount(
            1,
            (new FindServiceImpact())->find(
                $graph,
                'InvoiceRepository::save',
            ),
        );

        self::assertCount(
            1,
            (new FindServiceImpact())->find(
                $graph,
                'RegenerateInvoiceHandler',
            ),
        );
    }

    public function testItReturnsNothingForUnknownService(): void
    {
        self::assertSame(
            [],
            (new FindServiceImpact())->find(
                $this->graph(),
                'UnknownService',
            ),
        );
    }

    private function graph(): Graph
    {
        $graph = new Graph();

        $graph->addNode(new Node('route', NodeType::ROUTE, 'POST /invoices'));
        $graph->addNode(new Node(
            'controller',
            NodeType::CONTROLLER,
            'App\\Controller\\InvoiceController::create',
        ));
        $graph->addNode(new Node(
            'generate',
            NodeType::SERVICE,
            'App\\Service\\InvoiceGenerator::generate',
        ));
        $graph->addNode(new Node(
            'repository',
            NodeType::REPOSITORY,
            'App\\Repository\\InvoiceRepository::save',
        ));

        $graph->addEdge(new Edge('route', 'controller', EdgeType::INVOKES));
        $graph->addEdge(new Edge('controller', 'generate', EdgeType::CALLS));
        $graph->addEdge(new Edge('generate', 'repository', EdgeType::CALLS));

        $graph->addNode(new Node(
            'message',
            NodeType::MESSAGE,
            'App\\Message\\RegenerateInvoice',
        ));
        $graph->addNode(new Node(
            'handler',
            NodeType::HANDLER,
            'App\\MessageHandler\\RegenerateInvoiceHandler::__invoke',
        ));
        $graph->addNode(new Node(
            'regenerate',
            NodeType::SERVICE,
            'App\\Service\\InvoiceGenerator::regenerate',
        ));

        $graph->addEdge(new Edge('message', 'handler', EdgeType::HANDLED_BY));
        $graph->addEdge(new Edge('handler', 'regenerate', EdgeType::CALLS));

        return $graph;
    }
}
