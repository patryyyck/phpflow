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
                NodeType::MESSAGE => $this->classReference('message', $node->label()),
                NodeType::EXCEPTION => $this->exception($node),
                NodeType::CONTROLLER,
                NodeType::HANDLER,
                NodeType::REPOSITORY,
                NodeType::SERVICE => $this->callable($node),
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

    /** @return array{callable: array{class: string, method: string|null}} */
    private function callable(Node $node): array
    {
        [$class, $method] = array_pad(
            explode('::', $node->label(), 2),
            2,
            null,
        );

        return [
            'callable' => [
                'class' => ltrim($class, '\\'),
                'method' => $method,
            ],
        ];
    }

    /** @return array{exception: array{class: string, shortName: string}} */
    private function exception(Node $node): array
    {
        $class = preg_replace('/^throws\s+/i', '', trim($node->label()))
            ?? trim($node->label());

        return $this->classReference('exception', $class);
    }

    /**
     * @return array<string, array{class: string, shortName: string}>
     */
    private function classReference(string $key, string $class): array
    {
        $class = ltrim(trim($class), '\\');
        $parts = explode('\\', $class);

        return [
            $key => [
                'class' => $class,
                'shortName' => $parts[array_key_last($parts)] ?? $class,
            ],
        ];
    }
}
