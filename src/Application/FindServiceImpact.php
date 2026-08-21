<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Impact\ImpactPath;

final readonly class FindServiceImpact
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
                self::isCallableNode($node)
                && self::matches($node->label(), $query),
        ));

        return $this->deduplicate(
            $this->pathFinder->fromTargets($graph, $targets),
        );
    }

    private static function isCallableNode(Node $node): bool
    {
        return in_array(
            $node->type(),
            [
                NodeType::SERVICE,
                NodeType::REPOSITORY,
                NodeType::HANDLER,
                NodeType::CONTROLLER,
            ],
            true,
        );
    }

    private static function matches(string $label, string $query): bool
    {
        $label = ltrim($label, '\\');
        $query = ltrim($query, '\\');

        if (str_contains($query, '::')) {
            return strcasecmp($label, $query) === 0
                || strcasecmp(self::shortCallable($label), $query) === 0;
        }

        [$class] = explode('::', $label, 2);

        if (strcasecmp($class, $query) === 0) {
            return true;
        }

        return strcasecmp(self::shortClass($class), $query) === 0;
    }

    private static function shortCallable(string $callable): string
    {
        [$class, $method] = array_pad(
            explode('::', $callable, 2),
            2,
            '',
        );

        $shortClass = self::shortClass($class);

        return $method === ''
            ? $shortClass
            : $shortClass.'::'.$method;
    }

    private static function shortClass(string $class): string
    {
        $parts = explode('\\', $class);

        return $parts[array_key_last($parts)] ?? $class;
    }

    /**
     * @param list<ImpactPath> $paths
     * @return list<ImpactPath>
     */
    private function deduplicate(array $paths): array
    {
        $unique = [];

        foreach ($paths as $path) {
            $key = implode(
                "\0",
                array_map(
                    static fn (Node $node): string => $node->id(),
                    $path->nodes(),
                ),
            );

            $unique[$key] = $path;
        }

        return array_values($unique);
    }
}
