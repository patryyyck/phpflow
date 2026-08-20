<?php

declare(strict_types=1);

namespace App\ExpressionBranches;

interface ExpressionRepositoryInterface
{
    public function update(): object;
    public function insert(): object;
    public function fallback(): object;
}

interface ExpressionClientInterface
{
    public function register(): bool;
    public function notify(): bool;
}

final readonly class ExpressionBranchHandler
{
    public function __construct(
        private ExpressionRepositoryInterface $repository,
        private ExpressionClientInterface $client,
    ) {
    }

    public function ternary(bool $exists): object
    {
        return $exists
            ? $this->repository->update()
            : $this->repository->insert();
    }

    public function coalesce(?object $result): object
    {
        return $result ?? $this->repository->fallback();
    }

    public function shortCircuit(bool $enabled, bool $disabled): void
    {
        $enabled && $this->client->register();
        $disabled || $this->client->notify();
    }
}
