<?php

namespace App\MessageHandler;

use App\Message\DeleteUser;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class ExternalApiHandler
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function __invoke(DeleteUser $message): void
    {
        $this->httpClient->request(
            'POST',
            'https://api.example.test/users/delete',
        );
    }
}
