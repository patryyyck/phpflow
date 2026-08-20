<?php

declare(strict_types=1);

namespace PhpFlow\Ast;

final class ProjectIndex
{
    /** @var array<string, string|null> */
    private array $parents = [];

    /** @var array<string, list<string>> */
    private array $traits = [];

    /** @var array<string, list<string>> */
    private array $interfaces = [];

    /** @var array<string, true> */
    private array $declaredInterfaces = [];

    /** @var array<string, array<string, MethodDefinition>> */
    private array $methods = [];

    /** @var array<string, string> */
    private array $symbolFiles = [];

    /** @var array<string, true> */
    private array $testSymbols = [];

    public function addClass(
        string $class,
        ?string $parent,
        ?string $file = null,
        bool $testSymbol = false,
    ): void {
        $this->parents[$class] = $parent;

        if ($file !== null) {
            $this->symbolFiles[$class] = $file;
        }

        if ($testSymbol) {
            $this->testSymbols[$class] = true;
        }
    }

    public function addTrait(string $trait): void
    {
        $this->parents[$trait] ??= null;
    }

    public function addTraitUse(string $owner, string $trait): void
    {
        $this->traits[$owner][] = $trait;
    }

    public function addInterface(string $owner, string $interface): void
    {
        $this->interfaces[$owner][] = $interface;
    }

    public function markInterface(string $interface): void
    {
        $this->declaredInterfaces[$interface] = true;
    }

    public function isInterface(string $symbol): bool
    {
        return isset($this->declaredInterfaces[$symbol]);
    }

    /** @return list<string> */
    public function interfacesOf(string $owner): array
    {
        return $this->interfaces[$owner] ?? [];
    }

    public function implements(string $class, string $interface): bool
    {
        return $this->implementsRecursive($class, $interface, []);
    }

    /** @param array<string, true> $visited */
    private function implementsRecursive(string $symbol, string $interface, array $visited): bool
    {
        if (isset($visited[$symbol])) {
            return false;
        }

        $visited[$symbol] = true;

        foreach ($this->interfacesOf($symbol) as $implemented) {
            if ($implemented === $interface) {
                return true;
            }

            if ($this->implementsRecursive($implemented, $interface, $visited)) {
                return true;
            }
        }

        $parent = $this->parentOf($symbol);

        return $parent !== null
            && $this->implementsRecursive($parent, $interface, $visited);
    }

    /** @return list<string> */
    public function implementationsOf(string $interface): array
    {
        $implementations = [];

        foreach (array_keys($this->parents) as $symbol) {
            if (
                $symbol !== $interface
                && !$this->isInterface($symbol)
                && $this->implements($symbol, $interface)
            ) {
                $implementations[] = $symbol;
            }
        }

        sort($implementations);

        return $implementations;
    }

    public function uniqueImplementationOf(string $interface): ?string
    {
        $implementations = $this->implementationsOf($interface);

        if (count($implementations) === 1) {
            return $implementations[0];
        }

        $productionImplementations = array_values(array_filter(
            $implementations,
            fn (string $implementation): bool => !$this->isTestSymbol($implementation),
        ));

        return count($productionImplementations) === 1
            ? $productionImplementations[0]
            : null;
    }

    public function isTestSymbol(string $symbol): bool
    {
        return isset($this->testSymbols[$symbol]);
    }

    public function hasSymbol(string $symbol): bool
    {
        return array_key_exists($symbol, $this->parents);
    }

    public function parentOf(string $class): ?string
    {
        return $this->parents[$class] ?? null;
    }

    /** @return list<string> */
    public function traitsOf(string $owner): array
    {
        return $this->traits[$owner] ?? [];
    }

    /** @return list<string> */
    public function unresolvedReferencedSymbols(): array
    {
        $symbols = [];

        foreach ($this->parents as $parent) {
            if ($parent !== null && !$this->hasSymbol($parent)) {
                $symbols[$parent] = true;
            }
        }

        foreach ($this->traits as $traits) {
            foreach ($traits as $trait) {
                if (!$this->hasSymbol($trait)) {
                    $symbols[$trait] = true;
                }
            }
        }

        foreach ($this->interfaces as $interfaces) {
            foreach ($interfaces as $interface) {
                if (!$this->hasSymbol($interface)) {
                    $symbols[$interface] = true;
                }
            }
        }

        return array_keys($symbols);
    }

    public function addMethod(string $owner, MethodDefinition $method): void
    {
        $this->methods[$owner][$method->name()] = $method;
    }

    public function resolveMethod(string $class, string $method): ?MethodDefinition
    {
        return $this->resolveMethodRecursive($class, $method, []);
    }

    /** @param array<string, true> $visited */
    private function resolveMethodRecursive(string $symbol, string $method, array $visited): ?MethodDefinition
    {
        if (isset($visited[$symbol])) {
            return null;
        }

        $visited[$symbol] = true;

        if (isset($this->methods[$symbol][$method])) {
            return $this->methods[$symbol][$method];
        }

        foreach ($this->traitsOf($symbol) as $trait) {
            $resolved = $this->resolveMethodRecursive($trait, $method, $visited);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $parent = $this->parentOf($symbol);

        return $parent === null
            ? null
            : $this->resolveMethodRecursive($parent, $method, $visited);
    }
}
