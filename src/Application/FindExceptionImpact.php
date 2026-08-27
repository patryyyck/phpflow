<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Impact\ImpactPath;

final readonly class FindExceptionImpact
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
                $node->type() === NodeType::EXCEPTION
                && self::matches($node->label(), $query),
        ));

        return $this->pathFinder->fromTargets($graph, $targets);
    }

    private static function matches(string $label, string $query): bool
    {
        $exception = preg_replace('/^throws\s+/i', '', trim($label)) ?? trim($label);
        $exception = ltrim($exception, '\\');
        $query = ltrim($query, '\\');

        if (strcasecmp($exception, $query) === 0) {
            return true;
        }

        $parts = explode('\\', $exception);
        $shortName = $parts[array_key_last($parts)] ?? $exception;

        return strcasecmp($shortName, $query) === 0;
    }
}
