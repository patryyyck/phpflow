<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Application;

use PhpFlow\Application\ResolveMessageRouting;
use PhpFlow\Ast\ProjectIndexer;
use PhpFlow\Domain\Analysis\MessageRouting;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ResolveMessageRoutingTest extends TestCase
{
    public function testItResolvesRoutingDeclaredOnAnImplementedInterface(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $index = (new ProjectIndexer())->index($project);

        $transports = (new ResolveMessageRouting())->transportsFor(
            'App\\Event\\UserCreated',
            [
                new MessageRouting('App\\Event\\EventInterface', ['cred_event'], 'messenger.php'),
                new MessageRouting('App\\Event\\EventInterface', ['webhook_event'], 'messenger_webhook.php'),
            ],
            $index,
        );

        self::assertSame(['cred_event', 'webhook_event'], $transports);
    }
}
