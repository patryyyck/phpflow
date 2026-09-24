<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

/**
 * What PHPFlow saw of API Platform, including what it could not represent,
 * so a missing entry point is not read as a missing endpoint.
 */
final readonly class ApiPlatformCoverage
{
    public function __construct(
        private int $resources = 0,
        private int $operations = 0,
        private int $operationsWithTarget = 0,
        private int $unrecognizedOperations = 0,
        private int $resourcesWithoutOperations = 0,
    ) {
    }

    public function resources(): int { return $this->resources; }
    public function operations(): int { return $this->operations; }
    public function operationsWithTarget(): int { return $this->operationsWithTarget; }
    public function unrecognizedOperations(): int { return $this->unrecognizedOperations; }
    public function resourcesWithoutOperations(): int { return $this->resourcesWithoutOperations; }

    public function operationsWithoutTarget(): int
    {
        return $this->operations - $this->operationsWithTarget - $this->unrecognizedOperations;
    }
}
