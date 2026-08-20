<?php

declare(strict_types=1);

namespace PhpFlow\Console;

use PhpFlow\Domain\Impact\ImpactPath;

final readonly class ImpactPathRenderer
{
    /** @return list<string> */
    public function render(ImpactPath $path): array
    {
        $lines = [];

        foreach ($path->nodes() as $depth => $node) {
            $prefix = $depth === 0
                ? ''
                : str_repeat('    ', $depth - 1).'└── ';

            $lines[] = $prefix.$node->label();
        }

        return $lines;
    }
}
