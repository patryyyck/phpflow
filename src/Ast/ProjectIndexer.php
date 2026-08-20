<?php

declare(strict_types=1);

namespace PhpFlow\Ast;

use PhpFlow\Domain\Project;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class ProjectIndexer
{
    private readonly Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->createForNewestSupportedVersion();
    }

    public function index(Project $project): ProjectIndex
    {
        $index = new ProjectIndex();

        foreach ($project->sourceFiles() as $sourceFile) {
            $this->indexFile($sourceFile->path(), $index, $project->path());
        }

        $locator = new VendorSymbolLocator($project->path());
        $attempted = [];

        while (($symbols = $index->unresolvedReferencedSymbols()) !== []) {
            $progress = false;

            foreach ($symbols as $symbol) {
                if (isset($attempted[$symbol])) {
                    continue;
                }

                $attempted[$symbol] = true;
                $file = $locator->locate($symbol);

                if ($file === null) {
                    continue;
                }

                $this->indexFile($file, $index, $project->path());
                $progress = true;
            }

            if (!$progress) {
                break;
            }
        }

        return $index;
    }

    private function indexFile(string $file, ProjectIndex $index, string $projectPath): void
    {
        $ast = $this->resolvedAst($file);
        $finder = new NodeFinder();

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->isAnonymous() || $class->namespacedName === null) {
                continue;
            }

            $name = $class->namespacedName->toString();
            $index->addClass(
                $name,
                $class->extends?->toString(),
                $file,
                $this->isTestFile($file, $projectPath),
            );

            foreach ($class->implements as $interface) {
                $index->addInterface($name, $this->resolvedName($interface));
            }

            $this->indexTraitUses($name, $class->stmts, $index);
            $this->indexMethods($name, $class->getMethods(), $index);
        }

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Interface_::class) as $interface) {
            if ($interface->namespacedName === null) {
                continue;
            }

            $name = $interface->namespacedName->toString();
            $index->addClass(
                $name,
                null,
                $file,
                $this->isTestFile($file, $projectPath),
            );
            $index->markInterface($name);

            foreach ($interface->extends as $parentInterface) {
                $index->addInterface($name, $this->resolvedName($parentInterface));
            }
        }

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Trait_::class) as $trait) {
            if ($trait->namespacedName === null) {
                continue;
            }

            $name = $trait->namespacedName->toString();
            $index->addTrait($name);
            $this->indexTraitUses($name, $trait->stmts, $index);
            $this->indexMethods($name, $trait->getMethods(), $index);
        }
    }

    private function isTestFile(string $file, string $projectPath): bool
    {
        $projectPath = rtrim(str_replace('\\', '/', $projectPath), '/');
        $file = str_replace('\\', '/', $file);

        $relative = str_starts_with($file, $projectPath.'/')
            ? substr($file, strlen($projectPath) + 1)
            : $file;

        return str_starts_with($relative, 'tests/')
            || str_starts_with($relative, 'test/')
            || str_starts_with($relative, 'Tests/')
            || str_starts_with($relative, 'Test/');
    }

    private function resolvedName(Node\Name $name): string
    {
        return $name->getAttribute('resolvedName')?->toString()
            ?? $name->toString();
    }

    /** @param list<Node\Stmt> $statements */
    private function indexTraitUses(string $owner, array $statements, ProjectIndex $index): void
    {
        foreach ($statements as $statement) {
            if (!$statement instanceof Node\Stmt\TraitUse) {
                continue;
            }

            foreach ($statement->traits as $trait) {
                $index->addTraitUse($owner, $this->resolvedName($trait));
            }
        }
    }

    /** @param list<Node\Stmt\ClassMethod> $methods */
    private function indexMethods(string $owner, array $methods, ProjectIndex $index): void
    {
        foreach ($methods as $method) {
            $parameters = [];

            foreach ($method->params as $parameter) {
                $parameters[] = is_string($parameter->var->name) ? $parameter->var->name : '';
            }

            $index->addMethod(
                $owner,
                new MethodDefinition(
                    className: $owner,
                    name: $method->name->toString(),
                    parameters: $parameters,
                    dispatchedParameter: $this->findDispatchedParameter($method),
                ),
            );
        }
    }

    /** @return list<Node> */
    private function resolvedAst(string $file): array
    {
        $ast = $this->parser->parse(file_get_contents($file));

        if ($ast === null) {
            return [];
        }

        return (new NodeTraverser(new NameResolver()))->traverse($ast);
    }

    private function findDispatchedParameter(Node\Stmt\ClassMethod $method): ?string
    {
        $parameters = [];

        foreach ($method->params as $parameter) {
            if (is_string($parameter->var->name)) {
                $parameters[$parameter->var->name] = true;
            }
        }

        $calls = (new NodeFinder())->findInstanceOf(
            $method->stmts ?? [],
            Node\Expr\MethodCall::class,
        );

        foreach ($calls as $call) {
            if (
                !$call->name instanceof Node\Identifier
                || $call->name->toString() !== 'dispatch'
                || !isset($call->args[0])
            ) {
                continue;
            }

            $argument = $call->args[0]->value;

            if (
                $argument instanceof Node\Expr\Variable
                && is_string($argument->name)
                && isset($parameters[$argument->name])
            ) {
                return $argument->name;
            }
        }

        return null;
    }
}
