<?php

declare(strict_types=1);

namespace App\Consolidated;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ConsolidatedCommand {}

final readonly class ConsolidatedResponse {}

final class ConsolidatedProblem extends \RuntimeException {}

interface ConsolidatedRepositoryInterface
{
    public function findRequired(): array|false;
    public function insert(): void;
}

final readonly class ConsolidatedRepository implements ConsolidatedRepositoryInterface
{
    public function __construct(private Connection $connection) {}

    public function findRequired(): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT id FROM companies WHERE id = :id',
        );
    }

    public function insert(): void
    {
        $this->connection->insert('record_links', ['id' => 1]);
    }
}

interface ExternalGatewayInterface
{
    public function register(): void;
}

interface HttpClientInterface
{
    public function request(string $method, string $url): object;
}

final readonly class ExternalGateway implements ExternalGatewayInterface
{
    public function __construct(private HttpClientInterface $client) {}

    public function register(): void
    {
        $this->client->request('POST', 'https://example.test/v1/resources');
    }
}

#[AsMessageHandler]
final readonly class ConsolidatedHandler
{
    public function __construct(
        private ConsolidatedRepositoryInterface $repository,
        private ExternalGatewayInterface $gateway,
    ) {}

    public function __invoke(ConsolidatedCommand $command): ConsolidatedResponse
    {
        $company = $this->repository->findRequired();

        if (false === $company) {
            throw new ConsolidatedProblem('Missing company');
        }

        $this->gateway->register();
        $this->repository->insert();

        return new ConsolidatedResponse();
    }
}

final readonly class ConsolidatedController
{
    public function __construct(private MessageBusInterface $bus) {}

    #[Route('/consolidated', methods: ['POST'])]
    public function run(): void
    {
        $this->bus->dispatch(new ConsolidatedCommand());
    }
}
