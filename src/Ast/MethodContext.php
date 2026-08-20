<?php

declare(strict_types=1);

namespace PhpFlow\Ast;

final class MethodContext
{
    /** @var array<string, string> */
    private array $variables = [];

    /** @var array<string, string> */
    private array $strings = [];

    /** @var array<string, array{operation: string|null, target: string|null}> */
    private array $queryBuilders = [];

    public function rememberString(string $variable, string $value): void
    {
        $this->strings[$variable] = $value;
    }

    public function resolveString(string $variable): ?string
    {
        return $this->strings[$variable] ?? null;
    }

    public function rememberQueryBuilder(string $variable): void
    {
        $this->queryBuilders[$variable] = [
            'operation' => null,
            'target' => null,
        ];
    }

    public function updateQueryBuilder(
        string $variable,
        ?string $operation = null,
        ?string $target = null,
    ): void {
        if (!isset($this->queryBuilders[$variable])) {
            return;
        }

        if ($operation !== null) {
            $this->queryBuilders[$variable]['operation'] = $operation;
        }

        if ($target !== null) {
            $this->queryBuilders[$variable]['target'] = $target;
        }
    }

    /** @return array{operation: string|null, target: string|null}|null */
    public function queryBuilder(string $variable): ?array
    {
        return $this->queryBuilders[$variable] ?? null;
    }

    public function rememberObject(string $variable, string $className): void
    {
        $this->variables[$variable] = $className;
    }

    public function forget(string $variable): void
    {
        unset($this->variables[$variable], $this->strings[$variable], $this->queryBuilders[$variable]);
    }

    public function resolveObject(string $variable): ?string
    {
        return $this->variables[$variable] ?? null;
    }
}
