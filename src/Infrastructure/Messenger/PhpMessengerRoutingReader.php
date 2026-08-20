<?php

declare(strict_types=1);

namespace PhpFlow\Infrastructure\Messenger;

use PhpFlow\Domain\Analysis\MessageRouting;
use PhpFlow\Domain\Analysis\MessengerTransport;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class PhpMessengerRoutingReader
{
    private readonly Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return list<MessageRouting>
     */
    public function read(string $file): array
    {
        $ast = $this->parser->parse(file_get_contents($file));

        if ($ast === null) {
            return [];
        }

        $ast = (new NodeTraverser(new NameResolver()))->traverse($ast);

        $routing = [];

        foreach ($this->readArrayConfiguration($ast) as $item) {
            $routing[$item->message()] = $item->transports();
        }

        foreach ($this->readTypedConfigurator($ast) as $item) {
            $routing[$item->message()] = $item->transports();
        }

        $result = [];

        foreach ($routing as $message => $transports) {
            $result[] = new MessageRouting($message, $transports);
        }

        return $result;
    }

    /**
     * @return list<MessengerTransport>
     */
    public function readTransports(string $file): array
    {
        $ast = $this->parser->parse(file_get_contents($file));
        if ($ast === null) return [];

        $ast = (new NodeTraverser(new NameResolver()))->traverse($ast);
        $result = [];

        $nodes = $this->returnedClosureStatements($ast) ?? $ast;

        $this->collectTransports($nodes, basename($file), null, $result);

        return $result;
    }

    /**
     * @param list<Node> $ast
     * @return list<Node\Stmt>|null
     */
    private function returnedClosureStatements(array $ast): ?array
    {
        foreach ($ast as $node) {
            if (
                $node instanceof Node\Stmt\Return_
                && $node->expr instanceof Node\Expr\Closure
            ) {
                return $node->expr->stmts;
            }
        }

        return null;
    }

    /**
     * @param list<Node> $nodes
     * @param list<MessengerTransport> $result
     */
    private function collectTransports(
        array $nodes,
        string $source,
        ?string $environment,
        array &$result,
    ): void {
        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\If_) {
                $detectedEnvironment = $this->environmentCondition($node->cond);
                $this->collectTransports(
                    $node->stmts,
                    $source,
                    $detectedEnvironment ?? $environment,
                    $result,
                );

                foreach ($node->elseifs as $elseif) {
                    $elseifEnvironment = $this->environmentCondition($elseif->cond);
                    $this->collectTransports(
                        $elseif->stmts,
                        $source,
                        $elseifEnvironment ?? $environment,
                        $result,
                    );
                }

                if ($node->else !== null) {
                    $this->collectTransports($node->else->stmts, $source, $environment, $result);
                }

                continue;
            }

            if (!$node instanceof Node\Stmt\Expression) {
                continue;
            }

            $finder = new NodeFinder();

            /** @var list<Node\Expr\MethodCall> $calls */
            $calls = $finder->findInstanceOf([$node->expr], Node\Expr\MethodCall::class);

            foreach ($calls as $call) {
                if (
                    !$call->name instanceof Node\Identifier
                    || $call->name->toString() !== 'transport'
                    || !isset($call->args[0])
                    || !$call->args[0]->value instanceof Node\Scalar\String_
                ) {
                    continue;
                }

                $name = $call->args[0]->value->value;
                $dsn = $this->dsnChainedFromTransport($node, $call);

                $result[] = new MessengerTransport(
                    $name,
                    $dsn,
                    $source,
                    $environment,
                );
            }
        }
    }

    private function dsnChainedFromTransport(Node $root, Node\Expr\MethodCall $transportCall): ?string
    {
        $finder = new NodeFinder();

        foreach ($finder->findInstanceOf([$root], Node\Expr\MethodCall::class) as $candidate) {
            if (
                $candidate->name instanceof Node\Identifier
                && $candidate->name->toString() === 'dsn'
                && isset($candidate->args[0])
                && $candidate->args[0]->value instanceof Node\Scalar\String_
                && $candidate->var === $transportCall
            ) {
                return $candidate->args[0]->value->value;
            }
        }

        return null;
    }

    private function environmentCondition(Node\Expr $condition): ?string
    {
        if (!$condition instanceof Node\Expr\BinaryOp\Identical) {
            return null;
        }

        foreach ([[$condition->left, $condition->right], [$condition->right, $condition->left]] as [$value, $variable]) {
            if (
                $value instanceof Node\Scalar\String_
                && $variable instanceof Node\Expr\Variable
                && $variable->name === 'env'
            ) {
                return $value->value;
            }
        }

        return null;
    }

    /**
     * @param list<Node> $ast
     * @return list<MessageRouting>
     */
    private function readTypedConfigurator(array $ast): array
    {
        $finder = new NodeFinder();
        $result = [];

        /** @var list<Node\Expr\MethodCall> $calls */
        $calls = $finder->findInstanceOf($ast, Node\Expr\MethodCall::class);

        foreach ($calls as $call) {
            if (
                !$call->name instanceof Node\Identifier
                || $call->name->toString() !== 'senders'
                || !isset($call->args[0])
            ) {
                continue;
            }

            $routingCall = $call->var;

            if (
                !$routingCall instanceof Node\Expr\MethodCall
                || !$routingCall->name instanceof Node\Identifier
                || $routingCall->name->toString() !== 'routing'
                || !isset($routingCall->args[0])
            ) {
                continue;
            }

            $message = $this->messageNameFromExpression(
                $routingCall->args[0]->value,
            );

            if ($message === null) {
                continue;
            }

            $transports = $this->stringListFromExpression(
                $call->args[0]->value,
            );

            if ($transports === []) {
                continue;
            }

            $result[] = new MessageRouting($message, $transports);
        }

        return $result;
    }

    /**
     * @param list<Node> $ast
     * @return list<MessageRouting>
     */
    private function readArrayConfiguration(array $ast): array
    {
        $rootConfig = $this->findReturnedConfigArray($ast);

        if ($rootConfig === null) {
            return [];
        }

        $framework = $this->arrayValue($rootConfig, 'framework');
        $messenger = $framework instanceof Node\Expr\Array_
            ? $this->arrayValue($framework, 'messenger')
            : null;
        $routing = $messenger instanceof Node\Expr\Array_
            ? $this->arrayValue($messenger, 'routing')
            : null;

        if (!$routing instanceof Node\Expr\Array_) {
            return [];
        }

        $result = [];

        foreach ($routing->items as $item) {
            if ($item === null) {
                continue;
            }

            $message = $this->messageNameFromExpression($item->key);

            if ($message === null) {
                continue;
            }

            $transports = $this->transports($item->value);

            if ($transports !== []) {
                $result[] = new MessageRouting($message, $transports);
            }
        }

        return $result;
    }

    /**
     * @param list<Node> $ast
     */
    private function findReturnedConfigArray(array $ast): ?Node\Expr\Array_
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

    private function messageNameFromExpression(?Node\Expr $expression): ?string
    {
        if ($expression instanceof Node\Scalar\String_) {
            return ltrim($expression->value, '\\');
        }

        if (
            $expression instanceof Node\Expr\ClassConstFetch
            && $expression->name instanceof Node\Identifier
            && strtolower($expression->name->toString()) === 'class'
            && $expression->class instanceof Node\Name
        ) {
            return ltrim($expression->class->toString(), '\\');
        }

        return null;
    }

    private function arrayValue(Node\Expr\Array_ $array, string $key): ?Node\Expr
    {
        foreach ($array->items as $item) {
            if (
                $item !== null
                && $item->key instanceof Node\Scalar\String_
                && $item->key->value === $key
            ) {
                return $item->value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function transports(Node\Expr $value): array
    {
        if ($value instanceof Node\Scalar\String_) {
            return [$value->value];
        }

        if (!$value instanceof Node\Expr\Array_) {
            return [];
        }

        $senders = $this->arrayValue($value, 'senders');

        if ($senders instanceof Node\Expr\Array_) {
            return $this->stringList($senders);
        }

        return $this->stringList($value);
    }

    /**
     * @return list<string>
     */
    private function stringListFromExpression(Node\Expr $expression): array
    {
        if (!$expression instanceof Node\Expr\Array_) {
            return [];
        }

        return $this->stringList($expression);
    }

    /**
     * @return list<string>
     */
    private function stringList(Node\Expr\Array_ $array): array
    {
        $values = [];

        foreach ($array->items as $item) {
            if ($item?->value instanceof Node\Scalar\String_) {
                $values[] = $item->value->value;
            }
        }

        return array_values(array_unique($values));
    }
}
