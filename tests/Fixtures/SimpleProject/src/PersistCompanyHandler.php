<?php

namespace App\MessageHandler;

use App\Message\PersistCompany;
use App\Repository\CompanyRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PersistCompanyHandler
{
    public function __construct(
        private CompanyRepository $companyRepository,
    ) {
    }

    public function __invoke(PersistCompany $message): void
    {
        $this->companyRepository->save($message);
    }
}
