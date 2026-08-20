<?php

declare(strict_types=1);

namespace PhpFlow\Domain;

final readonly class Project
{
    /**
     * @param list<SourceFile> $sourceFiles
     */
    public function __construct(
        private string $path,
        private array $sourceFiles,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return list<SourceFile>
     */
    public function sourceFiles(): array
    {
        return $this->sourceFiles;
    }

    public function sourceFileCount(): int
    {
        return count($this->sourceFiles);
    }
}
