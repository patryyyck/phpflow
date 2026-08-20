<?php

declare(strict_types=1);

namespace App\ConditionalEffects;

interface AuditRepositoryInterface
{
    public function record(string $event): void;
}

interface PartnerClientInterface
{
    public function activate(): void;
    public function suspend(): void;
}

final readonly class ConditionalEffectsHandler
{
    public function __construct(
        private AuditRepositoryInterface $auditRepository,
        private PartnerClientInterface $partnerClient,
    ) {
    }

    public function run(string $state): void
    {
        if ($state === 'active') {
            $this->partnerClient->activate();
            $this->auditRepository->record('activated');
        } elseif ($state === 'suspended') {
            $this->partnerClient->suspend();
        } else {
            $this->auditRepository->record('ignored');
        }

        $this->auditRepository->record('completed');
    }
}
