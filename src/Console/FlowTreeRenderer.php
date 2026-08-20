<?php

declare(strict_types=1);

namespace PhpFlow\Console;

use PhpFlow\Domain\Graph\TraversalStep;

final class FlowTreeRenderer
{
    /**
     * @return list<string>
     */
    public function render(TraversalStep $step): array
    {
        $lines = [];
        $this->renderStep($step, $lines, '', true);

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function renderStep(
        TraversalStep $step,
        array &$lines,
        string $prefix,
        bool $last,
    ): void {
        $connector = $prefix === '' ? '' : ($last ? '└── ' : '├── ');
        $suffix = $step->cycle() ? ' [cycle]' : '';

        $lines[] = sprintf(
            '%s%s%s%s',
            $prefix,
            $connector,
            $step->node()->label(),
            $suffix,
        );

        $children = $step->children();
        $count = count($children);

        foreach ($children as $index => $child) {
            $childPrefix = $prefix;

            if ($prefix !== '') {
                $childPrefix .= $last ? '    ' : '│   ';
            } else {
                $childPrefix = '';
            }

            $this->renderStep(
                $child,
                $lines,
                $childPrefix === '' ? '    ' : $childPrefix,
                $index === $count - 1,
            );
        }
    }
}
