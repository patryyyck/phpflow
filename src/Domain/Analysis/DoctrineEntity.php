<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

/**
 * A Doctrine ORM entity and the table it is mapped to, when that table is
 * provable from mapping attributes. A null table means the name is left to
 * the configured naming strategy, which is not read.
 */
final readonly class DoctrineEntity
{
    public function __construct(
        private string $class,
        private ?string $table,
    ) {
    }

    public function class(): string { return $this->class; }
    public function table(): ?string { return $this->table; }
}
