<?php

declare(strict_types=1);

namespace PhpFlow\Console;

use InvalidArgumentException;

final readonly class ExportRouteOptions
{
    private function __construct(
        private ?string $route,
        private string $method,
        private int $maxDepth,
    ) {
    }

    public static function from(mixed $route, mixed $method, mixed $maxDepth): self
    {
        $normalizedRoute = is_string($route) ? trim($route) : '';

        if ($normalizedRoute === '') {
            $normalizedRoute = null;
        }

        $normalizedMethod = strtoupper(trim(is_string($method) ? $method : ''));

        if ($normalizedMethod === '') {
            throw new InvalidArgumentException('HTTP method must be a non-empty string.');
        }

        $depth = is_int($maxDepth) ? (string) $maxDepth : trim(is_string($maxDepth) ? $maxDepth : '');

        if ($depth === '' || preg_match('/^[1-9][0-9]*$/', $depth) !== 1) {
            throw new InvalidArgumentException('--max-depth must be a positive integer.');
        }

        return new self($normalizedRoute, $normalizedMethod, (int) $depth);
    }

    public function route(): ?string
    {
        return $this->route;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function maxDepth(): int
    {
        return $this->maxDepth;
    }

    public function startNodeId(): ?string
    {
        if ($this->route === null) {
            return null;
        }

        return sprintf('route:%s:%s', $this->method, $this->route);
    }
}
