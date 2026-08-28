<?php

declare(strict_types=1);

namespace PhpFlow\Console;

use PhpFlow\Domain\Diff\GraphDiff;

final readonly class GraphDiffRenderer
{
    /** @return list<string> */
    public function render(GraphDiff $diff): array
    {
        if (!$diff->hasChanges()) {
            return ['No graph changes detected.'];
        }

        $lines = ['Graph changes', ''];

        if ($diff->summary() !== []) {
            $lines[] = 'Summary';

            foreach ($diff->summary() as $category => $counts) {
                $lines[] = sprintf(
                    '  %-20s +%d  -%d',
                    $this->humanize($category),
                    $counts['added'],
                    $counts['removed'],
                );
            }

            $lines[] = '';
        }

        $this->appendNodes($lines, 'Added nodes', '+', $diff->addedNodes());
        $this->appendNodes($lines, 'Removed nodes', '-', $diff->removedNodes());
        $this->appendEdges($lines, 'Added edges', '+', $diff->addedEdges());
        $this->appendEdges($lines, 'Removed edges', '-', $diff->removedEdges());

        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @param list<array<string, mixed>> $nodes
     */
    private function appendNodes(array &$lines, string $title, string $prefix, array $nodes): void
    {
        if ($nodes === []) {
            return;
        }

        $lines[] = $title;

        foreach ($nodes as $node) {
            $lines[] = sprintf(
                '  %s [%s] %s',
                $prefix,
                (string) ($node['type'] ?? 'unknown'),
                (string) ($node['displayLabel'] ?? $node['label'] ?? $node['id'] ?? 'unknown'),
            );
        }

        $lines[] = '';
    }

    /**
     * @param list<string> $lines
     * @param list<array<string, mixed>> $edges
     */
    private function appendEdges(array &$lines, string $title, string $prefix, array $edges): void
    {
        if ($edges === []) {
            return;
        }

        $lines[] = $title;

        foreach ($edges as $edge) {
            $label = isset($edge['label']) && $edge['label'] !== ''
                ? sprintf(' (%s)', $edge['label'])
                : '';

            $lines[] = sprintf(
                '  %s %s --%s--> %s%s',
                $prefix,
                (string) ($edge['source'] ?? '?'),
                (string) ($edge['type'] ?? '?'),
                (string) ($edge['target'] ?? '?'),
                $label,
            );
        }

        $lines[] = '';
    }

    private function humanize(string $category): string
    {
        $spaced = preg_replace('/(?<!^)[A-Z]/', ' $0', $category) ?? $category;

        return ucfirst($spaced);
    }
}
