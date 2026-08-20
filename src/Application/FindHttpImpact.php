<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Impact\ImpactPath;

final readonly class FindHttpImpact
{
    public function __construct(
        private FindImpactPaths $pathFinder = new FindImpactPaths(),
    ) {
    }

    /** @return list<ImpactPath> */
    public function find(Graph $graph, string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $targets = array_values(array_filter(
            $graph->nodes(),
            static fn (Node $node): bool =>
                $node->type() === NodeType::HTTP_ENDPOINT
                && str_contains(
                    strtolower($node->label()),
                    strtolower($query),
                ),
        ));

        return $this->pathFinder->fromTargets($graph, $targets);
    }
}
