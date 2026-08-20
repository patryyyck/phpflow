<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\MethodContext;
use PHPUnit\Framework\TestCase;

final class MethodContextTest extends TestCase
{
    public function testItRemembersStringValuesWithoutDynamicProperties(): void
    {
        $context = new MethodContext();

        $context->rememberString('sql', 'SELECT id FROM company');

        self::assertSame(
            'SELECT id FROM company',
            $context->resolveString('sql'),
        );

        $context->forget('sql');

        self::assertNull($context->resolveString('sql'));
    }
}
