<?php

declare(strict_types=1);

namespace App\LocalHttp;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class DirectoryRegistrationClient
{
    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(param: 'directory.base_url')]
        private string $baseUrl,
    ) {
    }

    public function getRegistrationStatus(string $number): array
    {
        return $this->fetchAllResults($number);
    }

    private function fetchAllResults(string $number): array
    {
        $results = [];
        $retries = 0;

        do {
            $response = $this->client->request(
                'POST',
                $this->baseUrl.'/v2/directory/search',
                [
                    'json' => ['number' => $number],
                ],
            );

            ++$retries;
        } while ($retries < 2);

        return $results;
    }
}


use Symfony\Component\Routing\Attribute\Route;

final readonly class DirectoryRegistrationController
{
    public function __construct(
        private DirectoryRegistrationClient $client,
    ) {
    }

    #[Route('/local-http-flow', methods: ['POST'])]
    public function run(): void
    {
        $this->client->getRegistrationStatus('123456789');
    }
}
