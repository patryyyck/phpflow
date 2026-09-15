<?php

declare(strict_types=1);

namespace App\FirstClassCallable;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ReportNormalizer
{
    public function __construct(
        private HttpClientInterface $client,
    ) {
    }

    public function normalize(array $rows): array
    {
        // First-class callable syntax on local methods: the call carries a
        // VariadicPlaceholder instead of an argument list.
        $rows = array_filter($rows, $this->isNotEmpty(...));

        return array_map($this->decorate(...), $rows);
    }

    public function fetch(): void
    {
        $this->client->request('GET', 'https://reports.example.com/v1/rows');
    }

    private function isNotEmpty(?string $row): bool
    {
        return $row !== null && $row !== '';
    }

    private function decorate(string $row): string
    {
        return strtoupper($row);
    }
}

final readonly class ReportController
{
    public function __construct(
        private ReportNormalizer $normalizer,
    ) {
    }

    #[Route('/first-class-callable', methods: ['GET'])]
    public function run(array $rows): array
    {
        // Same syntax on an injected service method.
        $normalize = $this->normalizer->normalize(...);

        $this->normalizer->fetch();

        return $normalize($rows);
    }
}
