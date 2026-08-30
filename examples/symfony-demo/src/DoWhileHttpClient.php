<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class DoWhileHttpClient
{
    private const MAX_RESULTS_PER_PAGE = 50;

    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(param: 'directory.base_url')]
        private string $baseUrl,
    ) {
    }

    public function search(array $filters): void
    {
        $offset = 0;
        $retries = 0;

        do {
            $response = $this->client->request(
                'POST',
                $this->baseUrl.'/v2/directory/search',
                [
                    'json' => [
                        'filters' => $filters,
                        'limit' => self::MAX_RESULTS_PER_PAGE,
                        'offset' => $offset > 0 ? $offset : null,
                    ],
                ],
            );

            $statusCode = $response->getStatusCode();
            $offset += self::MAX_RESULTS_PER_PAGE;
            ++$retries;
        } while (206 === $statusCode && $retries < 3);
    }
}
