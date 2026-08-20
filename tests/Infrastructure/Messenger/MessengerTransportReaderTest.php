<?php
declare(strict_types=1);
namespace PhpFlow\Tests\Infrastructure\Messenger;

use PhpFlow\Infrastructure\Messenger\PhpMessengerRoutingReader;
use PHPUnit\Framework\TestCase;

final class MessengerTransportReaderTest extends TestCase
{
    public function testItPreservesDefaultAndEnvironmentSpecificTransportConfiguration(): void
    {
        $items = (new PhpMessengerRoutingReader())->readTransports(
            __DIR__.'/../../Fixtures/SimpleProject/config/packages/messenger.php',
        );

        $async = array_values(array_filter(
            $items,
            static fn ($transport): bool => $transport->name() === 'async',
        ));

        self::assertCount(2, $async);
        self::assertSame('sqs://default', $async[0]->dsn());
        self::assertNull($async[0]->environment());
        self::assertSame('sync://', $async[1]->dsn());
        self::assertSame('test', $async[1]->environment());
    }
}
