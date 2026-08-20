<?php

declare(strict_types=1);

namespace PhpFlow\Application\Contract;

use PhpFlow\Domain\Project;

interface ProjectScanner
{
    public function scan(string $path): Project;
}
