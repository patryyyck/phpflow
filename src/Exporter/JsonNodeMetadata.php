<?php

declare(strict_types=1);

namespace PhpFlow\Exporter;

use PhpFlow\Application\ImpactEntryPoints;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;

final readonly class JsonNodeMetadata
{
    public function __construct(
        private ImpactEntryPoints $entryPoints = new ImpactEntryPoints(),
    ) {
    }

    public function displayLabel(Node $node): string
    {
        return match ($node->type()) {
            NodeType::CONTROLLER,
            NodeType::HANDLER,
            NodeType::REPOSITORY,
            NodeType::SERVICE => $this->shortCallable($node->label()),
            NodeType::MESSAGE => $this->shortClass($node->label()),
            NodeType::EXCEPTION => 'throws '.$this->shortClass(
                preg_replace('/^throws\s+/i', '', trim($node->label()))
                    ?? trim($node->label()),
            ),
            default => $node->label(),
        };
    }

    /** @return array<string, mixed> */
    public function for(Graph $graph, Node $node): array
    {
        $metadata = [
            'entryPoint' => $this->entryPoints->isEntryPoint($graph, $node),
        ];

        return array_merge(
            $metadata,
            match ($node->type()) {
                NodeType::ROUTE => $this->route($node),
                NodeType::HTTP_ENDPOINT => $this->http($node),
                NodeType::DATABASE => $this->database($node),
                NodeType::MESSAGE => $this->classReference($graph, 'message', $node->label()),
                NodeType::EXCEPTION => $this->exception($graph, $node),
                NodeType::CONTROLLER,
                NodeType::HANDLER,
                NodeType::REPOSITORY,
                NodeType::SERVICE => $this->callable($graph, $node),
                default => [],
            },
        );
    }

    /** @return array{route: array{method: string, path: string}} */
    private function route(Node $node): array
    {
        $method = '';
        $path = '';

        if (str_starts_with($node->id(), 'route:')) {
            $parts = explode(':', $node->id(), 3);
            $method = $parts[1] ?? '';
            $path = $parts[2] ?? '';
        }

        if ($method === '' || $path === '') {
            [$method, $path] = array_pad(
                preg_split('/\s+/', trim($node->label()), 2) ?: [],
                2,
                '',
            );
        }

        return [
            'route' => [
                'method' => strtoupper($method),
                'path' => $path,
            ],
        ];
    }

    /** @return array{http: array{method: string, url: string}} */
    private function http(Node $node): array
    {
        [$method, $url] = array_pad(
            preg_split('/\s+/', trim($node->label()), 2) ?: [],
            2,
            '',
        );

        return [
            'http' => [
                'method' => strtoupper($method),
                'url' => $url,
            ],
        ];
    }

    /** @return array{database: array{operation: string, target: string}} */
    private function database(Node $node): array
    {
        [$operation, $target] = array_pad(
            preg_split('/\s+/', trim($node->label()), 2) ?: [],
            2,
            '',
        );

        return [
            'database' => [
                'operation' => strtoupper($operation),
                'target' => $target,
            ],
        ];
    }

    /** @return array{callable: array<string, string|null>} */
    private function callable(Graph $graph, Node $node): array
    {
        [$class, $method] = array_pad(
            explode('::', $node->label(), 2),
            2,
            null,
        );

        $class = ltrim($class, '\\');
        [$namespace, $shortName] = $this->classParts($class);

        return [
            'callable' => [
                'class' => $class,
                'shortName' => $shortName,
                'namespace' => $namespace,
                'method' => $method,
                'file' => $graph->symbolFile($class),
            ],
        ];
    }

    /** @return array{exception: array<string, string|null>} */
    private function exception(Graph $graph, Node $node): array
    {
        $class = preg_replace('/^throws\s+/i', '', trim($node->label()))
            ?? trim($node->label());

        return $this->classReference($graph, 'exception', $class);
    }

    /** @return array<string, array<string, string|null>> */
    private function classReference(Graph $graph, string $key, string $class): array
    {
        $class = ltrim(trim($class), '\\');
        [$namespace, $shortName] = $this->classParts($class);

        return [
            $key => [
                'class' => $class,
                'shortName' => $shortName,
                'namespace' => $namespace,
                'file' => $graph->symbolFile($class),
            ],
        ];
    }

    /** @return array{string, string} */
    private function classParts(string $class): array
    {
        $position = strrpos($class, '\\');

        if ($position === false) {
            return ['', $class];
        }

        return [
            substr($class, 0, $position),
            substr($class, $position + 1),
        ];
    }

    private function shortClass(string $class): string
    {
        return $this->classParts(ltrim(trim($class), '\\'))[1];
    }

    private function shortCallable(string $callable): string
    {
        [$class, $method] = array_pad(
            explode('::', $callable, 2),
            2,
            null,
        );

        $short = $this->shortClass($class);

        return $method === null || $method === ''
            ? $short
            : $short.'::'.$method;
    }
}
