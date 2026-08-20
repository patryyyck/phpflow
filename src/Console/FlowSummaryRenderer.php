<?php

declare(strict_types=1);

namespace PhpFlow\Console;

use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Domain\Graph\TraversalStep;

final class FlowSummaryRenderer
{
    /**
     * @return list<string>
     */
    public function render(TraversalStep $flow): array
    {
        $groups = [
            'HTTP' => [],
            'DATABASE' => [],
            'MAIL' => [],
            'FILESYSTEM' => [],
            'CACHE' => [],
            'EXCEPTIONS' => [],
            'RESPONSES' => [],
            'RETURNS' => [],
        ];

        $seen = [];
        $this->collect($flow, $groups, $seen);

        $lines = [];

        foreach ($groups as $heading => $labels) {
            if ($labels === []) {
                continue;
            }

            if ($lines !== []) {
                $lines[] = '';
            }

            $lines[] = $heading;

            foreach ($labels as $label) {
                $lines[] = '  '.$label;
            }
        }

        return $lines;
    }

    /**
     * @param array<string, list<string>> $groups
     * @param array<string, true> $seen
     */
    private function collect(
        TraversalStep $step,
        array &$groups,
        array &$seen,
    ): void {
        $node = $step->node();

        if (!isset($seen[$node->id()])) {
            $heading = match ($node->type()) {
                NodeType::HTTP_ENDPOINT => 'HTTP',
                NodeType::DATABASE => 'DATABASE',
                NodeType::MAIL => 'MAIL',
                NodeType::FILESYSTEM => 'FILESYSTEM',
                NodeType::CACHE => 'CACHE',
                NodeType::EXCEPTION => 'EXCEPTIONS',
                NodeType::HTTP_RESPONSE => 'RESPONSES',
                NodeType::RETURN_VALUE => 'RETURNS',
                default => null,
            };

            if ($heading !== null) {
                $groups[$heading][] = $this->summaryLabel($node->type(), $node->label());
                $seen[$node->id()] = true;
            }
        }

        if ($step->cycle()) {
            return;
        }

        foreach ($step->children() as $child) {
            $this->collect($child, $groups, $seen);
        }
    }

    private function summaryLabel(NodeType $type, string $label): string
    {
        return match ($type) {
            NodeType::EXCEPTION => preg_replace('/^throws\s+/', '', $label) ?? $label,
            NodeType::RETURN_VALUE => preg_replace('/^returns\s+/', '', $label) ?? $label,
            default => $label,
        };
    }
}
