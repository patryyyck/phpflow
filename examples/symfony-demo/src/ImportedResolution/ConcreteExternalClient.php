<?php

declare(strict_types=1);

namespace App\ImportedResolution\Infra;

use App\ImportedResolution\Domain\ExternalClientInterface;

final class ConcreteExternalClient implements ExternalClientInterface
{
    public function call(): void
    {
    }
}
