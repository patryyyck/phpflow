<?php
namespace App\Sync;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncServiceHandler
{
    public function __construct(private ExternalSyncClientInterface $client) {}

    public function __invoke(object $message): void
    {
        $this->client->register($message);
    }
}
