<?php

declare(strict_types=1);

namespace PhpFlow\Ast;

use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Reads, from application source only, the literal arguments that a `new X(...)`
 * expression ends up passing to the constructor of one of its ancestors.
 *
 * It follows `extends` through the project index and `parent::__construct()`
 * calls, and is deliberately not a general evaluator: a value is resolved only
 * when it is a literal, a `Foo::class`, a constructor parameter (passed or
 * defaulted) forwarded as is, or such a parameter with a literal `??` fallback.
 * Anything else stays unresolved, and so does the whole chain as soon as it
 * would need a vendor constructor signature (positional arguments to
 * `parent::__construct()`), a spread, or a constructor that never calls its
 * parent.
 */
final readonly class ConstructorArgumentResolver
{
    private const int MAX_DEPTH = 16;

    public function __construct(private ProjectIndex $index)
    {
    }

    /**
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $arguments Call-site arguments of `new $class(...)`
     * @param string                                        $ancestor  Class whose constructor receives the result
     */
    public function resolve(string $class, array $arguments, string $ancestor): ?ResolvedArguments
    {
        $named = [];
        $positional = [];

        foreach ($arguments as $argument) {
            if (!$argument instanceof Node\Arg || $argument->unpack) {
                return null;
            }

            if ($argument->name !== null) {
                $named[$argument->name->toString()] = $this->evaluate($argument->value, []);
            } else {
                $positional[] = $this->evaluate($argument->value, []);
            }
        }

        return $this->resolveClass($class, $named, $positional, $ancestor, 0);
    }

    /**
     * @param array<string, ?array{0: mixed}> $named      Resolved value of each argument, null when unresolved
     * @param list<?array{0: mixed}>          $positional
     */
    private function resolveClass(
        string $class,
        array $named,
        array $positional,
        string $ancestor,
        int $depth,
    ): ?ResolvedArguments {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        // A vendor constructor signature is never read, whatever the version.
        if ($this->index->isVendorSymbol($class)) {
            return null;
        }

        $constructor = $this->index->constructorOf($class);
        $parent = $this->index->parentOf($class);

        if ($parent === null) {
            return null;
        }

        if ($constructor === null) {
            // The inherited constructor is the parent's: arguments pass through.
            return $this->descend($parent, $named, $positional, $ancestor, $depth);
        }

        $scope = $this->scope($constructor, $named, $positional);

        if ($scope === null) {
            return null;
        }

        $call = $this->parentConstructorCall($constructor);

        if ($call === null) {
            return null;
        }

        $forwarded = [];

        foreach ($call->args as $argument) {
            if (!$argument instanceof Node\Arg || $argument->unpack || $argument->name === null) {
                return null;
            }

            $forwarded[$argument->name->toString()] = $this->evaluate($argument->value, $scope);
        }

        return $this->descend($parent, $forwarded, [], $ancestor, $depth);
    }

    /**
     * @param array<string, ?array{0: mixed}> $named
     * @param list<?array{0: mixed}>          $positional
     */
    private function descend(
        string $parent,
        array $named,
        array $positional,
        string $ancestor,
        int $depth,
    ): ?ResolvedArguments {
        if ($parent === $ancestor) {
            // Mapping positional arguments needs the ancestor's own signature.
            if ($positional !== []) {
                return null;
            }

            return new ResolvedArguments($named);
        }

        return $this->resolveClass($parent, $named, $positional, $ancestor, $depth + 1);
    }

    /**
     * @param array<string, ?array{0: mixed}> $named
     * @param list<?array{0: mixed}>          $positional
     *
     * @return ?array<string, ?array{0: mixed}> Value of each parameter, null when it cannot be known
     */
    private function scope(Node\Stmt\ClassMethod $constructor, array $named, array $positional): ?array
    {
        $scope = [];

        foreach ($constructor->params as $position => $parameter) {
            if (!$parameter->var instanceof Node\Expr\Variable || !is_string($parameter->var->name) || $parameter->variadic) {
                return null;
            }

            $name = $parameter->var->name;

            if (array_key_exists($name, $named)) {
                $scope[$name] = $named[$name];
            } elseif (array_key_exists($position, $positional)) {
                $scope[$name] = $positional[$position];
            } elseif ($parameter->default !== null) {
                $scope[$name] = $this->evaluate($parameter->default, []);
            } else {
                $scope[$name] = null;
            }
        }

        if (count($positional) > count($constructor->params)) {
            return null;
        }

        return $scope;
    }

    private function parentConstructorCall(Node\Stmt\ClassMethod $constructor): ?Node\Expr\StaticCall
    {
        $calls = array_values(array_filter(
            (new NodeFinder())->findInstanceOf($constructor->stmts ?? [], Node\Expr\StaticCall::class),
            static fn (Node\Expr\StaticCall $call): bool => $call->class instanceof Node\Name
                && strtolower($call->class->toString()) === 'parent'
                && $call->name instanceof Node\Identifier
                && strtolower($call->name->toString()) === '__construct',
        ));

        return count($calls) === 1 ? $calls[0] : null;
    }

    /**
     * @param array<string, ?array{0: mixed}> $scope
     *
     * @return ?array{0: mixed} A one-element list holding the value, or null when unresolved
     */
    private function evaluate(Node\Expr $expression, array $scope): ?array
    {
        if ($expression instanceof Node\Scalar\String_) {
            return [$expression->value];
        }

        if ($expression instanceof Node\Scalar\Int_ || $expression instanceof Node\Scalar\Float_) {
            return [$expression->value];
        }

        if ($expression instanceof Node\Expr\ConstFetch) {
            return match (strtolower($expression->name->toString())) {
                'true' => [true],
                'false' => [false],
                'null' => [null],
                default => null,
            };
        }

        if (
            $expression instanceof Node\Expr\ClassConstFetch
            && $expression->name instanceof Node\Identifier
            && strtolower($expression->name->toString()) === 'class'
            && $expression->class instanceof Node\Name
            && !in_array(strtolower($expression->class->toString()), ['self', 'static', 'parent'], true)
        ) {
            return [new ClassReference(
                $expression->class->getAttribute('resolvedName')?->toString() ?? $expression->class->toString(),
            )];
        }

        if ($expression instanceof Node\Expr\Variable && is_string($expression->name)) {
            return $scope[$expression->name] ?? null;
        }

        if ($expression instanceof Node\Expr\BinaryOp\Coalesce) {
            $left = $this->evaluate($expression->left, $scope);

            if ($left === null) {
                return null;
            }

            return $left[0] !== null ? $left : $this->evaluate($expression->right, $scope);
        }

        return null;
    }
}
