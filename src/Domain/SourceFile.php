<?php

declare(strict_types=1);

namespace PhpFlow\Domain;

final readonly class SourceFile
{
    public function __construct(
        private string $path,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }
}
