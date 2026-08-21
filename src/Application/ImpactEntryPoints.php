<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;

final readonly class ImpactEntryPoints
{
    /** @return list<Node> */
    public function find(Graph $graph): array
    {
        return array_values(array_filter(
            $graph->nodes(),
            fn (Node $node): bool => $this->isEntryPoint($graph, $node),
        ));
    }

    public function isEntryPoint(Graph $graph, Node $node): bool
    {
        if ($node->type() === NodeType::ROUTE) {
            return true;
        }

        if ($node->type() !== NodeType::MESSAGE) {
            return false;
        }

        // A message dispatched by an already-modelled application path is not
        // an independent entry point. Messages with no incoming graph edge are
        // externally-triggered/standalone processes from PHPFlow's viewpoint.
        return $graph->incomingEdges($node->id()) === [];
    }
}
