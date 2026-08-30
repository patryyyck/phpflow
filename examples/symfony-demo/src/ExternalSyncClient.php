<?php
namespace App\Sync;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ExternalSyncClient implements ExternalSyncClientInterface
{
    public function __construct(
        #[Autowire(service: SyncHttpClient::class)]
        private object $client,
        #[Autowire(param: 'sync.base_url')]
        private string $baseUrl,
    ) {}

    public function register(object $input): object
    {
        return $this->client->request('POST', $this->baseUrl.'/v1/resources');
    }
}
