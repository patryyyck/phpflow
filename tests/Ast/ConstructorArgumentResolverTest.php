<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ClassReference;
use PhpFlow\Ast\ConstructorArgumentResolver;
use PhpFlow\Ast\ProjectIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class ConstructorArgumentResolverTest extends TestCase
{
    private const string BASE = <<<'PHP'
        <?php
        namespace App;

        class Base {}
        class Plain extends Base {}

        class Forwarding extends Base
        {
            public function __construct(?string $path = null, $target = null, string $kind = Kind::class)
            {
                parent::__construct(path: $path, target: $target ?? Fallback::class, kind: $kind, flag: true);
            }
        }

        class Child extends Forwarding
        {
            public function __construct(?string $path = null)
            {
                parent::__construct(path: $path, target: Other::class);
            }
        }

        class Positional extends Base
        {
            public function __construct() { parent::__construct('x'); }
        }

        class NoParentCall extends Base
        {
            public function __construct() {}
        }

        class Conditional extends Base
        {
            public function __construct(bool $flag = true)
            {
                if ($flag) {
                    parent::__construct(target: Foo::class);
                }
            }
        }

        class InLoop extends Base
        {
            public function __construct()
            {
                foreach ([1] as $item) {
                    parent::__construct(target: Foo::class);
                }
            }
        }

        class InTernary extends Base
        {
            public function __construct(bool $flag = true)
            {
                $flag ? parent::__construct(target: Foo::class) : null;
            }
        }

        class TwoBranches extends Base
        {
            public function __construct(bool $flag = true)
            {
                parent::__construct(target: Foo::class);

                if ($flag) {
                    parent::__construct(target: Bar::class);
                }
            }
        }

        class EarlyReturn extends Base
        {
            public function __construct(bool $flag = true)
            {
                if ($flag) {
                    return;
                }

                parent::__construct(target: Foo::class);
            }
        }

        class GuardedThenCalled extends Base
        {
            public function __construct(?string $path = null)
            {
                if ($path === '') {
                    throw new \InvalidArgumentException();
                }

                parent::__construct(path: $path, target: Foo::class);
            }
        }

        class ReassignedBeforeCall extends Base
        {
            public function __construct(?string $path = null, $target = Foo::class)
            {
                $target = Bar::class;
                parent::__construct(path: $path, target: $target);
            }
        }

        class DestructuredBeforeCall extends Base
        {
            public function __construct($target = Foo::class)
            {
                [$target] = [Bar::class];
                parent::__construct(target: $target);
            }
        }

        class AliasedBeforeCall extends Base
        {
            public function __construct($target = Foo::class)
            {
                $alias = &$target;
                $alias = Bar::class;
                parent::__construct(target: $target);
            }
        }

        class VariableVariableBeforeCall extends Base
        {
            public function __construct(string $name = 'target', $target = Foo::class)
            {
                $$name = Bar::class;
                parent::__construct(target: $target);
            }
        }

        class ReassignedAfterCall extends Base
        {
            public function __construct($target = Foo::class)
            {
                parent::__construct(target: $target);
                $target = Bar::class;
            }
        }

        class OverwrittenAfterCall extends Base
        {
            public function __construct(?string $path = null)
            {
                parent::__construct(path: $path, target: Foo::class);
                $this->target = Bar::class;
                $this->kind ??= Kind::class;
            }
        }

        class OverwritesForwarded extends Forwarding
        {
            public function __construct()
            {
                parent::__construct(path: '/o');
                $this->target = Bar::class;
            }
        }

        class DynamicPropertyAfterCall extends Base
        {
            public function __construct(string $property = 'target')
            {
                parent::__construct(target: Foo::class);
                $this->{$property} = Bar::class;
            }
        }

        class Computed extends Base
        {
            public function __construct() { parent::__construct(target: self::pick()); }
        }
        PHP;

    public function testItResolvesCallSiteArgumentsThroughAConstructorlessClass(): void
    {
        $resolved = $this->resolve('Plain', 'path: "/a", target: Foo::class');

        self::assertSame('/a', $resolved?->value('path'));
        self::assertEquals(new ClassReference('App\\Foo'), $resolved->value('target'));
    }

    public function testItAppliesParameterDefaultsAndLiteralFallbacks(): void
    {
        $resolved = $this->resolve('Forwarding', 'path: "/a"');

        self::assertEquals(new ClassReference('App\\Fallback'), $resolved?->value('target'));
        self::assertEquals(new ClassReference('App\\Kind'), $resolved->value('kind'));
        self::assertTrue($resolved->value('flag'));
    }

    public function testACallSiteValueWinsOverTheFallback(): void
    {
        self::assertEquals(new ClassReference('App\\Given'), $this->resolve('Forwarding', 'target: Given::class')?->value('target'));
    }

    public function testAnExplicitNullTakesTheFallback(): void
    {
        self::assertEquals(new ClassReference('App\\Fallback'), $this->resolve('Forwarding', 'target: null')?->value('target'));
    }

    public function testTheClosestLiteralInTheChainWins(): void
    {
        self::assertEquals(new ClassReference('App\\Other'), $this->resolve('Child', 'path: "/c"')?->value('target'));
    }

    public function testAnArgumentNotPassedIsAbsent(): void
    {
        self::assertFalse($this->resolve('Plain', 'path: "/a"')?->isPassed('target'));
    }

    public function testPositionalCallSiteArgumentsAreMappedOntoTheApplicationConstructor(): void
    {
        self::assertSame('/p', $this->resolve('Forwarding', '"/p"')?->value('path'));
    }

    public function testItStaysUnresolvedWhenAVendorSignatureWouldBeNeeded(): void
    {
        self::assertNull($this->resolve('Positional', ''));
        self::assertNull($this->resolve('Plain', '"/positional"'));
        self::assertNull($this->resolve('NoParentCall', ''));
    }

    public function testItStaysUnresolvedWhenTheParentCallMayNotRun(): void
    {
        self::assertNull($this->resolve('Conditional', ''));
        self::assertNull($this->resolve('InLoop', ''));
        self::assertNull($this->resolve('InTernary', ''));
        self::assertNull($this->resolve('TwoBranches', ''));
        self::assertNull($this->resolve('EarlyReturn', ''));
    }

    public function testAThrowingGuardBeforeTheParentCallKeepsItUnconditional(): void
    {
        self::assertEquals(new ClassReference('App\\Foo'), $this->resolve('GuardedThenCalled', 'path: "/g"')?->value('target'));
    }

    public function testAParameterReassignedBeforeTheParentCallIsUnresolved(): void
    {
        $resolved = $this->resolve('ReassignedBeforeCall', 'path: "/r"');

        self::assertTrue($resolved?->isPassed('target'));
        self::assertFalse($resolved->isResolved('target'));
        self::assertSame('/r', $resolved->value('path'));

        foreach (['DestructuredBeforeCall', 'AliasedBeforeCall', 'VariableVariableBeforeCall'] as $class) {
            self::assertFalse($this->resolve($class, '')?->isResolved('target'), $class);
        }
    }

    public function testAParameterReassignedAfterTheParentCallKeepsItsForwardedValue(): void
    {
        self::assertEquals(new ClassReference('App\\Foo'), $this->resolve('ReassignedAfterCall', '')?->value('target'));
    }

    public function testAPropertyWrittenAfterTheParentCallIsUnresolved(): void
    {
        $resolved = $this->resolve('OverwrittenAfterCall', 'path: "/w"');

        self::assertTrue($resolved?->isPassed('target'));
        self::assertFalse($resolved->isResolved('target'));
        self::assertTrue($resolved->isPassed('kind'));
        self::assertFalse($resolved->isResolved('kind'));
        self::assertSame('/w', $resolved->value('path'));
    }

    public function testAPropertyWrittenBelowTheAncestorOverridesTheWholeChain(): void
    {
        $resolved = $this->resolve('OverwritesForwarded', '');

        self::assertFalse($resolved?->isResolved('target'));
        self::assertEquals(new ClassReference('App\\Kind'), $resolved->value('kind'));
    }

    public function testADynamicPropertyWriteAfterTheParentCallLeavesTheChainUnresolved(): void
    {
        self::assertNull($this->resolve('DynamicPropertyAfterCall', ''));
    }

    public function testAComputedValueIsPassedButUnresolved(): void
    {
        $resolved = $this->resolve('Computed', '');

        self::assertTrue($resolved?->isPassed('target'));
        self::assertFalse($resolved->isResolved('target'));
    }

    public function testItNeverFollowsAClassIndexedFromVendor(): void
    {
        $index = $this->index();
        $index->addClass('App\\Vendored', 'App\\Base', null, false, true);

        self::assertNull((new ConstructorArgumentResolver($index))->resolve('App\\Vendored', [], 'Stop'));
    }

    private function resolve(string $class, string $arguments): ?\PhpFlow\Ast\ResolvedArguments
    {
        $index = $this->index();
        $statements = $this->parse('<?php namespace App; new X('.$arguments.');');
        $new = (new NodeFinder())->findFirstInstanceOf($statements, Node\Expr\New_::class);

        return (new ConstructorArgumentResolver($index))->resolve('App\\'.$class, $new->args, 'App\\Base');
    }

    private function index(): ProjectIndex
    {
        $index = new ProjectIndex();

        foreach ((new NodeFinder())->findInstanceOf($this->parse(self::BASE), Node\Stmt\Class_::class) as $class) {
            $name = $class->namespacedName->toString();
            $index->addClass($name, $class->extends?->toString());

            if (($constructor = $class->getMethod('__construct')) !== null) {
                $index->addConstructor($name, $constructor);
            }
        }

        return $index;
    }

    /** @return list<Node> */
    private function parse(string $code): array
    {
        $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($code);

        return (new NodeTraverser(new NameResolver()))->traverse($ast);
    }
}
