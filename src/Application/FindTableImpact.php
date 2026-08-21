<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Impact\ImpactPath;

final readonly class FindTableImpact
{
    public function __construct(
        private FindImpactPaths $pathFinder = new FindImpactPaths(),
    ) {
    }

    /** @return list<ImpactPath> */
    public function find(
        Graph $graph,
        string $table,
        ?string $operation = null,
    ): array {
        $table = self::normalizeIdentifier($table);
        $operation = $operation === null
            ? null
            : strtoupper(trim($operation));

        if ($table === '') {
            return [];
        }

        $targets = array_values(array_filter(
            $graph->nodes(),
            static function (Node $node) use ($table, $operation): bool {
                if ($node->type() !== NodeType::DATABASE) {
                    return false;
                }

                [$nodeOperation, $target] = self::databaseDescriptor($node->label());

                if ($operation !== null && $nodeOperation !== $operation) {
                    return false;
                }

                return self::matchesTable($target, $table);
            },
        ));

        return $this->pathFinder->fromTargets($graph, $targets);
    }

    /** @return array{string, string} */
    private static function databaseDescriptor(string $label): array
    {
        $parts = preg_split('/\s+/', trim($label), 2);

        return [
            strtoupper($parts[0] ?? ''),
            self::normalizeIdentifier($parts[1] ?? ''),
        ];
    }

    private static function matchesTable(string $candidate, string $query): bool
    {
        if ($candidate === $query) {
            return true;
        }

        return self::baseTable($candidate) === self::baseTable($query);
    }

    private static function baseTable(string $identifier): string
    {
        $parts = explode('.', $identifier);

        return $parts[array_key_last($parts)] ?? '';
    }

    private static function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return '';
        }

        $parts = array_map(
            static fn (string $part): string =>
                trim(trim($part), "`\"[]"),
            explode('.', $identifier),
        );

        return strtolower(implode('.', $parts));
    }
}
