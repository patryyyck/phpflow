<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Infrastructure\Messenger;

use PhpFlow\Infrastructure\Messenger\PhpMessengerRoutingReader;
use PHPUnit\Framework\TestCase;

final class PhpMessengerRoutingReaderTest extends TestCase
{
    public function testItReadsMessengerRoutingFromTypedSymfonyPhpConfiguration(): void
    {
        $items = (new PhpMessengerRoutingReader())->read(
            __DIR__.'/../../Fixtures/SimpleProject/config/packages/messenger.php',
        );

        $byMessage = [];

        foreach ($items as $item) {
            $byMessage[$item->message()] = $item->transports();
        }

        self::assertSame(['async'], $byMessage['App\Event\EventInterface']);
        self::assertSame(
            ['async', 'mail'],
            $byMessage['App\Message\DeleteUser'],
        );
    }
}
