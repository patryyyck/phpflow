<?php
declare(strict_types=1);
namespace PhpFlow\Tests\Infrastructure\Messenger;

use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PHPUnit\Framework\TestCase;

final class MessengerRoutingReaderTest extends TestCase
{
    public function testItReadsMessengerRouting(): void
    {
        $items = (new MessengerRoutingReader())->read(__DIR__.'/../../Fixtures/SimpleProject');
        $byMessage = [];
        foreach ($items as $item) $byMessage[$item->message()] = $item->transports();

        self::assertSame(['async'], $byMessage['App\Event\UserCreated']);
        self::assertSame(['async', 'async_low'], $byMessage['App\Message\DeleteUser']);
    }
}
