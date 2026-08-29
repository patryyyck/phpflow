<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use InvalidArgumentException;
use JsonException;
use PhpFlow\Domain\Diff\GraphDiff;

final readonly class CompareGraphExports
{
    /** @var list<string> */
    private const array SUMMARY_TYPES = [
        'route',
        'database',
        'http_endpoint',
        'message',
        'exception',
        'service',
        'repository',
        'handler',
        'controller',
    ];

    public function compare(string $beforeJson, string $afterJson): GraphDiff
    {
        $before = $this->decode($beforeJson, 'before');
        $after = $this->decode($afterJson, 'after');

        if ($before['schemaVersion'] !== $after['schemaVersion']) {
            throw new InvalidArgumentException(sprintf(
                'Cannot compare PHPFlow graph schemas %s and %s.',
                $before['schemaVersion'],
                $after['schemaVersion'],
            ));
        }

        $beforeNodes = $this->indexNodes($before['nodes']);
        $afterNodes = $this->indexNodes($after['nodes']);

        $addedNodes = $this->difference($afterNodes, $beforeNodes);
        $removedNodes = $this->difference($beforeNodes, $afterNodes);

        $beforeEdges = $this->indexEdges($before['edges']);
        $afterEdges = $this->indexEdges($after['edges']);

        $addedEdges = $this->difference($afterEdges, $beforeEdges);
        $removedEdges = $this->difference($beforeEdges, $afterEdges);

        return new GraphDiff(
            array_values($addedNodes),
            array_values($removedNodes),
            array_values($addedEdges),
            array_values($removedEdges),
            $this->summary($addedNodes, $removedNodes),
        );
    }

    /**
     * @return array{schemaVersion: string, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function decode(string $json, string $name): array
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                sprintf('Invalid %s PHPFlow graph JSON: %s', $name, $exception->getMessage()),
                previous: $exception,
            );
        }

        if (!is_array($data)
            || !is_string($data['schemaVersion'] ?? null)
            || !is_array($data['nodes'] ?? null)
            || !is_array($data['edges'] ?? null)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s PHPFlow graph JSON contract.',
                $name,
            ));
        }

        return [
            'schemaVersion' => $data['schemaVersion'],
            'nodes' => array_values($data['nodes']),
            'edges' => array_values($data['edges']),
        ];
    }

    /**
     * Node identity is the stable graph node ID. If an existing node changes
     * payload, treat the previous representation as removed and the new one as added.
     *
     * @param list<array<string, mixed>> $nodes
     * @return array<string, array<string, mixed>>
     */
    private function indexNodes(array $nodes): array
    {
        $indexed = [];

        foreach ($nodes as $node) {
            if (!is_array($node) || !is_string($node['id'] ?? null)) {
                throw new InvalidArgumentException('PHPFlow graph contains a node without a valid id.');
            }

            $indexed[$node['id'].'|'.$this->fingerprint($node)] = $node;
        }

        ksort($indexed);

        return $indexed;
    }

    /**
     * @param list<array<string, mixed>> $edges
     * @return array<string, array<string, mixed>>
     */
    private function indexEdges(array $edges): array
    {
        $indexed = [];

        foreach ($edges as $edge) {
            if (!is_array($edge)
                || !is_string($edge['source'] ?? null)
                || !is_string($edge['target'] ?? null)
                || !is_string($edge['type'] ?? null)
            ) {
                throw new InvalidArgumentException('PHPFlow graph contains an invalid edge.');
            }

            $indexed[$this->fingerprint($edge)] = $edge;
        }

        ksort($indexed);

        return $indexed;
    }

    /**
     * @param array<string, array<string, mixed>> $left
     * @param array<string, array<string, mixed>> $right
     * @return array<string, array<string, mixed>>
     */
    private function difference(array $left, array $right): array
    {
        return array_diff_key($left, $right);
    }

    /**
     * @param array<string, array<string, mixed>> $added
     * @param array<string, array<string, mixed>> $removed
     * @return array<string, array{added: int, removed: int}>
     */
    private function summary(array $added, array $removed): array
    {
        $summary = [];

        foreach (self::SUMMARY_TYPES as $type) {
            $addedCount = $this->countType($added, $type);
            $removedCount = $this->countType($removed, $type);

            if ($addedCount !== 0 || $removedCount !== 0) {
                $summary[$this->summaryKey($type)] = [
                    'added' => $addedCount,
                    'removed' => $removedCount,
                ];
            }
        }

        return $summary;
    }

    /** @param array<string, array<string, mixed>> $nodes */
    private function countType(array $nodes, string $type): int
    {
        return count(array_filter(
            $nodes,
            static fn (array $node): bool => ($node['type'] ?? null) === $type,
        ));
    }

    private function summaryKey(string $type): string
    {
        return match ($type) {
            'database' => 'databaseEffects',
            'http_endpoint' => 'externalHttp',
            default => $type.'s',
        };
    }

    /** @param array<string, mixed> $value */
    private function fingerprint(array $value): string
    {
        return hash('sha256', json_encode(
            $this->normalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
