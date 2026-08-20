<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Application\Contract\ProjectScanner;
use PhpFlow\Domain\Project;

final readonly class ScanProject
{
    public function __construct(
        private ProjectScanner $scanner,
    ) {
    }

    public function scan(string $path): Project
    {
        return $this->scanner->scan($path);
    }
}
