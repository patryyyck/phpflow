<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class UnionMessageHandlerTest extends TestCase
{
    public function testItRegistersAHandlerForEveryNamedUnionType(): void
    {
        $analysis = (new ProjectAstAnalyzer())->analyze(
            (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject'),
        );

        $handlers = array_values(array_filter(
            $analysis->messageHandlers(),
            static fn ($handler): bool =>
                $handler->handler() === 'App\\UnionHandler\\UnionCommandHandler::__invoke',
        ));

        self::assertCount(2, $handlers);

        $messages = array_map(
            static fn ($handler): string => $handler->message(),
            $handlers,
        );

        sort($messages);

        self::assertSame(
            [
                'App\\UnionHandler\\FirstCommand',
                'App\\UnionHandler\\SecondCommand',
            ],
            $messages,
        );
    }
}
