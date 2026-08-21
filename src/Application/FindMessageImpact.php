<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Impact\ImpactPath;

final readonly class FindMessageImpact
{
    public function __construct(
        private FindImpactPaths $pathFinder = new FindImpactPaths(),
    ) {
    }

    /** @return list<ImpactPath> */
    public function find(Graph $graph, string $query): array
    {
        $query = strtolower(trim($query));

        if ($query === '') {
            return [];
        }

        $targets = array_values(array_filter(
            $graph->nodes(),
            static fn (Node $node): bool =>
                $node->type() === NodeType::MESSAGE
                && self::matches($node->label(), $query),
        ));

        return $this->pathFinder->fromTargets($graph, $targets);
    }

    private static function matches(string $message, string $query): bool
    {
        $message = strtolower(ltrim($message, '\\'));

        if ($message === ltrim($query, '\\')) {
            return true;
        }

        $parts = explode('\\', $message);
        $shortName = $parts[array_key_last($parts)] ?? $message;

        return $shortName === ltrim($query, '\\');
    }
}
