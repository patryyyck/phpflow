<?php

declare(strict_types=1);

namespace App\Tests;

use App\Repository\CompanyRepository;

final class MockCompanyRepository implements CompanyRepository
{
    public function save(object $company): void
    {
    }
}
