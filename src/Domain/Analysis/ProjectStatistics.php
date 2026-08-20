<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class ProjectStatistics
{
    public function __construct(
        private int $classes,
        private int $interfaces,
        private int $traits,
        private int $enums,
    ) {
    }

    public function classes(): int { return $this->classes; }
    public function interfaces(): int { return $this->interfaces; }
    public function traits(): int { return $this->traits; }
    public function enums(): int { return $this->enums; }
}
