<?php

declare(strict_types=1);

namespace PhpFlow\Infrastructure\Symfony;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

final class SymfonyServiceAliasReader
{
    private readonly Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * Reads Symfony service aliases for the base configuration and, optionally,
     * one explicit environment override.
     *
     * With no environment, environment-specific files such as services_test.php
     * are deliberately ignored. This mirrors a production/default inspection and
     * prevents test mocks from overriding application services.
     *
     * @return array<string, string>
     */
    public function read(string $projectPath, ?string $environment = null): array
    {
        $configDirectory = $projectPath.'/config';

        if (!is_dir($configDirectory)) {
            return [];
        }

        $files = [];

        $base = $configDirectory.'/services.php';

        if (is_file($base)) {
            $files[] = $base;
        }

        $finder = (new Finder())
            ->files()
            ->in($configDirectory)
            ->name('*.services.php')
            ->sortByName();

        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        if ($environment !== null && $environment !== '') {
            $environmentFile = sprintf(
                '%s/services_%s.php',
                $configDirectory,
                $environment,
            );

            if (is_file($environmentFile)) {
                $files[] = $environmentFile;
            }
        }

        $aliases = [];

        foreach ($files as $file) {
            foreach ($this->readFile($file) as $service => $target) {
                $aliases[$service] = $target;
            }
        }

        return $aliases;
    }

    /**
     * @return array<string, string>
     */
    private function readFile(string $file): array
    {
        $ast = $this->parser->parse(file_get_contents($file));

        if ($ast === null) {
            return [];
        }

        $ast = (new NodeTraverser(new NameResolver()))->traverse($ast);

        return array_merge(
            $this->readFluentAliases($ast),
            $this->readArrayConfigAliases($ast),
        );
    }

    /**
     * @param list<Node> $ast
     * @return array<string, string>
     */
    private function readFluentAliases(array $ast): array
    {
        $finder = new NodeFinder();
        $aliases = [];

        /** @var list<Node\Expr\MethodCall> $calls */
        $calls = $finder->findInstanceOf($ast, Node\Expr\MethodCall::class);

        foreach ($calls as $call) {
            if (
                !$call->name instanceof Node\Identifier
                || $call->name->toString() !== 'alias'
            ) {
                continue;
            }

            if (isset($call->args[0], $call->args[1])) {
                $service = $this->className($call->args[0]->value);
                $target = $this->className($call->args[1]->value);

                if ($service !== null && $target !== null) {
                    $aliases[$service] = $target;
                }

                continue;
            }

            if (
                isset($call->args[0])
                && $call->var instanceof Node\Expr\MethodCall
                && $call->var->name instanceof Node\Identifier
                && $call->var->name->toString() === 'set'
                && isset($call->var->args[0])
            ) {
                $service = $this->className($call->args[0]->value);
                $target = $this->className($call->var->args[0]->value);

                if ($service !== null && $target !== null) {
                    $aliases[$service] = $target;
                }
            }
        }

        return $aliases;
    }

    /**
     * @param list<Node> $ast
     * @return array<string, string>
     */
    private function readArrayConfigAliases(array $ast): array
    {
        $root = $this->returnedConfigArray($ast);

        if ($root === null) {
            return [];
        }

        $services = $this->arrayValue($root, 'services');

        if (!$services instanceof Node\Expr\Array_) {
            return [];
        }

        /** @var array<string, string> $definitions */
        $definitions = [];

        /** @var array<string, string> $references */
        $references = [];

        foreach ($services->items as $item) {
            if ($item === null) {
                continue;
            }

            $id = $this->serviceId($item->key);

            if ($id === null || $id === '_defaults') {
                continue;
            }

            // Example:
            // 'foo.impl' => ['class' => Concrete::class]
            if ($item->value instanceof Node\Expr\Array_) {
                $class = $this->arrayValue($item->value, 'class');

                if ($class instanceof Node\Expr) {
                    $className = $this->className($class);

                    if ($className !== null) {
                        $definitions[$id] = $className;
                    }
                }

                continue;
            }

            // Example:
            // Interface::class => service(Concrete::class)
            // Interface::class => service('foo.impl')
            $reference = $this->serviceReference($item->value);

            if ($reference !== null) {
                $references[$id] = $reference;
            }
        }

        $aliases = [];

        foreach ($references as $service => $target) {
            $resolved = $this->resolveReference($target, $definitions, $references);

            if ($resolved !== null) {
                $aliases[$service] = $resolved;
            }
        }

        return $aliases;
    }

    /**
     * @param list<Node> $ast
     */
    private function returnedConfigArray(array $ast): ?Node\Expr\Array_
    {
        $finder = new NodeFinder();

        /** @var list<Node\Stmt\Return_> $returns */
        $returns = $finder->findInstanceOf($ast, Node\Stmt\Return_::class);

        foreach ($returns as $return) {
            $expr = $return->expr;

            if ($expr instanceof Node\Expr\Array_) {
                return $expr;
            }

            if (
                $expr instanceof Node\Expr\StaticCall
                && $expr->name instanceof Node\Identifier
                && $expr->name->toString() === 'config'
                && isset($expr->args[0])
                && $expr->args[0]->value instanceof Node\Expr\Array_
            ) {
                return $expr->args[0]->value;
            }
        }

        return null;
    }

    private function arrayValue(Node\Expr\Array_ $array, string $key): ?Node\Expr
    {
        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            $itemKey = $this->serviceId($item->key);

            if ($itemKey === $key) {
                return $item->value;
            }
        }

        return null;
    }

    private function serviceId(?Node\Expr $expression): ?string
    {
        if ($expression instanceof Node\Scalar\String_) {
            return ltrim($expression->value, '\\');
        }

        return $this->className($expression);
    }

    private function serviceReference(Node\Expr $expression): ?string
    {
        if (
            $expression instanceof Node\Expr\FuncCall
            && $expression->name instanceof Node\Name
            && isset($expression->args[0])
        ) {
            $function = ltrim($expression->name->toString(), '\\');

            if (
                $function === 'service'
                || $function === 'Symfony\\Component\\DependencyInjection\\Loader\\Configurator\\service'
            ) {
                return $this->serviceId($expression->args[0]->value);
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $definitions
     * @param array<string, string> $references
     * @param array<string, true> $visited
     */
    private function resolveReference(
        string $target,
        array $definitions,
        array $references,
        array $visited = [],
    ): ?string {
        if (isset($visited[$target])) {
            return null;
        }

        $visited[$target] = true;

        if (isset($definitions[$target])) {
            return $definitions[$target];
        }

        if (isset($references[$target])) {
            return $this->resolveReference(
                $references[$target],
                $definitions,
                $references,
                $visited,
            );
        }

        // A class FQCN used directly as a service id.
        if (str_contains($target, '\\')) {
            return ltrim($target, '\\');
        }

        return null;
    }

    private function className(?Node\Expr $expression): ?string
    {
        if (
            $expression instanceof Node\Expr\ClassConstFetch
            && $expression->name instanceof Node\Identifier
            && strtolower($expression->name->toString()) === 'class'
            && $expression->class instanceof Node\Name
        ) {
            return ltrim($expression->class->toString(), '\\');
        }

        if ($expression instanceof Node\Scalar\String_) {
            return ltrim($expression->value, '\\');
        }

        return null;
    }
}
