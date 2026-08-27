<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\FindExceptionImpact;
use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PHPUnit\Framework\TestCase;

final class FindExceptionImpactTest extends TestCase
{
    public function testItFindsRouteThatCanThrowException(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route', NodeType::ROUTE, 'POST /payments'));
        $graph->addNode(new Node(
            'service',
            NodeType::SERVICE,
            'App\\Payment\\PaymentGateway::charge',
        ));
        $graph->addNode(new Node(
            'exception',
            NodeType::EXCEPTION,
            'throws App\\Payment\\PaymentFailed',
        ));

        $graph->addEdge(new Edge('route', 'service', EdgeType::CALLS));
        $graph->addEdge(new Edge('service', 'exception', EdgeType::CALLS));

        $paths = (new FindExceptionImpact())->find(
            $graph,
            'App\\Payment\\PaymentFailed',
        );

        self::assertCount(1, $paths);
        self::assertSame('POST /payments', $paths[0]->root()->label());
        self::assertSame(
            'throws App\\Payment\\PaymentFailed',
            $paths[0]->effect()->label(),
        );
    }

    public function testItAcceptsShortExceptionName(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route', NodeType::ROUTE, 'POST /payments'));
        $graph->addNode(new Node(
            'exception',
            NodeType::EXCEPTION,
            'throws App\\Payment\\PaymentFailed',
        ));
        $graph->addEdge(new Edge('route', 'exception', EdgeType::CALLS));

        self::assertCount(
            1,
            (new FindExceptionImpact())->find($graph, 'PaymentFailed'),
        );
    }

    public function testItPreservesConditionalBranchInImpactPath(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node('route', NodeType::ROUTE, 'POST /payments'));
        $graph->addNode(new Node(
            'condition',
            NodeType::CONDITION,
            'IF $paymentFailed',
        ));
        $graph->addNode(new Node(
            'exception',
            NodeType::EXCEPTION,
            'throws App\\Payment\\PaymentFailed',
        ));

        $graph->addEdge(new Edge('route', 'condition', EdgeType::CALLS));
        $graph->addEdge(new Edge('condition', 'exception', EdgeType::CALLS));

        $paths = (new FindExceptionImpact())->find(
            $graph,
            'PaymentFailed',
        );

        self::assertCount(1, $paths);

        self::assertSame(
            [
                'POST /payments',
                'IF $paymentFailed',
                'throws App\\Payment\\PaymentFailed',
            ],
            array_map(
                static fn (Node $node): string => $node->label(),
                $paths[0]->nodes(),
            ),
        );
    }

    public function testItFindsStandaloneMessengerProcessThatCanThrow(): void
    {
        $graph = new Graph();

        $graph->addNode(new Node(
            'message',
            NodeType::MESSAGE,
            'App\\Message\\ImportPayments',
        ));
        $graph->addNode(new Node(
            'handler',
            NodeType::HANDLER,
            'App\\MessageHandler\\ImportPaymentsHandler::__invoke',
        ));
        $graph->addNode(new Node(
            'exception',
            NodeType::EXCEPTION,
            'throws App\\Payment\\ImportFailed',
        ));

        $graph->addEdge(new Edge('message', 'handler', EdgeType::HANDLED_BY));
        $graph->addEdge(new Edge('handler', 'exception', EdgeType::CALLS));

        $paths = (new FindExceptionImpact())->find(
            $graph,
            'ImportFailed',
        );

        self::assertCount(1, $paths);
        self::assertSame(
            'App\\Message\\ImportPayments',
            $paths[0]->root()->label(),
        );
    }

    public function testItReturnsNothingForUnknownException(): void
    {
        self::assertSame(
            [],
            (new FindExceptionImpact())->find(
                new Graph(),
                'UnknownException',
            ),
        );
    }
}
