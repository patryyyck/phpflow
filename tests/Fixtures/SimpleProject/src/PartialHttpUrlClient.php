<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PartialHttpUrlClient
{
    private const TOKEN_ENDPOINT = '/oauth/token';

    public function __construct(
        private HttpClientInterface $client,
        private string $baseUrl,
        #[Autowire(param: 'api.base_url')]
        private string $configuredBaseUrl,
    ) {
    }

    public function unresolvedBase(string $identificationNumber): object
    {
        return $this->client->request(
            'POST',
            $this->baseUrl.'/v2/directory/search',
            [
                'json' => [
                    'id' => $identificationNumber,
                ],
            ],
        );
    }

    public function configuredBase(string $id): object
    {
        return $this->client->request(
            'GET',
            $this->configuredBaseUrl.'/v1/resources/'.$id,
        );
    }

    public function interpolated(string $id): object
    {
        return $this->client->request(
            'GET',
            "/v1/resources/$id/status",
        );
    }

    public function variableUrl(string $id): object
    {
        $url = '/v1/resources/'.$id;

        return $this->client->request('GET', $url);
    }
    public function classConstantEndpoint(): object
    {
        return $this->client->request(
            'POST',
            $this->configuredBaseUrl.self::TOKEN_ENDPOINT,
        );
    }

    public function sprintfUrl(string $id): object
    {
        $url = \sprintf(
            '%s/v2/directory/search',
            $this->configuredBaseUrl,
        );

        return $this->client->request(
            'POST',
            $url,
            ['json' => ['id' => $id]],
        );
    }


}
