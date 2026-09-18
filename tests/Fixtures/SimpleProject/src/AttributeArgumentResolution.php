<?php

declare(strict_types=1);

namespace App\AttributeArguments;

use App\Repository\CompanyRepository;
use App\Sync\ExternalSyncClient;

#[ResourceBinding(
    repository: CompanyRepository::class,
    client: ExternalSyncClient::class,
)]
final class BoundResource
{
}
