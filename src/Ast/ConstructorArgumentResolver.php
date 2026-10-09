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
 * `parent::__construct()`), a spread, or a constructor that does not call its
 * parent unconditionally. A parameter written before the parent call, or a
 * `$this` property written after it, leaves that value unresolved.
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

        $statements = $constructor->stmts ?? [];
        $position = $this->parentConstructorCallPosition($statements);

        if ($position === null) {
            return null;
        }

        $call = $statements[$position]->expr;
        $scope = $this->forgetReassignedParameters($scope, array_slice($statements, 0, $position + 1));
        $overwritten = $this->overwrittenProperties(array_slice($statements, $position + 1));

        if ($overwritten === null || !$call instanceof Node\Expr\StaticCall) {
            return null;
        }

        $forwarded = [];

        foreach ($call->args as $argument) {
            if (!$argument instanceof Node\Arg || $argument->unpack || $argument->name === null) {
                return null;
            }

            $forwarded[$argument->name->toString()] = $this->evaluate($argument->value, $scope);
        }

        return $this->descend($parent, $forwarded, [], $ancestor, $depth)?->withUnresolved($overwritten);
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

    /**
     * Position of the `parent::__construct()` statement, only when it runs on
     * every path: it is the only call in the body, a top-level statement, and no
     * statement before it can return early.
     *
     * @param array<Node\Stmt> $statements
     */
    private function parentConstructorCallPosition(array $statements): ?int
    {
        $finder = new NodeFinder();
        $calls = $finder->find($statements, $this->isParentConstructorCall(...));

        if (count($calls) !== 1) {
            return null;
        }

        foreach ($statements as $position => $statement) {
            if ($statement instanceof Node\Stmt\Expression && $statement->expr === $calls[0]) {
                return $position;
            }

            if ($finder->findFirstInstanceOf($statement, Node\Stmt\Return_::class) !== null) {
                return null;
            }
        }

        return null;
    }

    /**
     * A parameter written before the parent call no longer holds its passed or
     * default value, and its new value is not propagated.
     *
     * @param array<string, ?array{0: mixed}> $scope
     * @param array<Node>                     $statements
     *
     * @return array<string, ?array{0: mixed}>
     */
    private function forgetReassignedParameters(array $scope, array $statements): array
    {
        foreach ($this->writtenTargets($statements) as $target) {
            if (!$target instanceof Node\Expr\Variable) {
                continue;
            }

            if (!is_string($target->name)) {
                return array_map(static fn (): null => null, $scope);
            }

            if (array_key_exists($target->name, $scope)) {
                $scope[$target->name] = null;
            }
        }

        return $scope;
    }

    /**
     * Properties written on `$this` after the parent call, which replace what the
     * ancestor constructor received. Null when a property name is dynamic.
     *
     * @param array<Node> $statements
     *
     * @return ?list<string>
     */
    private function overwrittenProperties(array $statements): ?array
    {
        $properties = [];

        foreach ($this->writtenTargets($statements) as $target) {
            if (
                !$target instanceof Node\Expr\PropertyFetch
                || !$target->var instanceof Node\Expr\Variable
                || $target->var->name !== 'this'
            ) {
                continue;
            }

            if (!$target->name instanceof Node\Identifier) {
                return null;
            }

            $properties[] = $target->name->toString();
        }

        return $properties;
    }

    /**
     * Variables and properties written by a statically recognizable write.
     * A by-reference argument to a call is not seen.
     *
     * @param array<Node> $statements
     *
     * @return list<Node\Expr>
     */
    private function writtenTargets(array $statements): array
    {
        $targets = [];

        foreach ((new NodeFinder())->find($statements, static fn (): bool => true) as $node) {
            $written = match (true) {
                $node instanceof Node\Expr\Assign,
                $node instanceof Node\Expr\AssignOp,
                $node instanceof Node\Expr\PreInc,
                $node instanceof Node\Expr\PreDec,
                $node instanceof Node\Expr\PostInc,
                $node instanceof Node\Expr\PostDec,
                $node instanceof Node\Stmt\Catch_ => [$node->var],
                // Both sides are aliased, so a later write through either changes the other.
                $node instanceof Node\Expr\AssignRef => [$node->var, $node->expr],
                $node instanceof Node\ClosureUse => $node->byRef ? [$node->var] : [],
                $node instanceof Node\Stmt\Foreach_ => [$node->keyVar, $node->valueVar],
                $node instanceof Node\Stmt\Unset_, $node instanceof Node\Stmt\Global_ => $node->vars,
                $node instanceof Node\Stmt\Static_ => array_map(static fn (Node\StaticVar $var): Node\Expr => $var->var, $node->vars),
                default => [],
            };

            foreach ($written as $target) {
                if ($target instanceof Node\Expr) {
                    array_push($targets, ...$this->writtenBases($target));
                }
            }
        }

        return $targets;
    }

    /**
     * `[$a, $b] = ...` writes each element, `$a['k'] = ...` writes `$a`.
     *
     * @return list<Node\Expr>
     */
    private function writtenBases(Node\Expr $target): array
    {
        if ($target instanceof Node\Expr\List_ || $target instanceof Node\Expr\Array_) {
            $bases = [];

            foreach ($target->items as $item) {
                if ($item !== null) {
                    array_push($bases, ...$this->writtenBases($item->value));
                }
            }

            return $bases;
        }

        if ($target instanceof Node\Expr\ArrayDimFetch) {
            return $this->writtenBases($target->var);
        }

        return [$target];
    }

    private function isParentConstructorCall(Node $node): bool
    {
        return $node instanceof Node\Expr\StaticCall
            && $node->class instanceof Node\Name
            && strtolower($node->class->toString()) === 'parent'
            && $node->name instanceof Node\Identifier
            && strtolower($node->name->toString()) === '__construct';
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
