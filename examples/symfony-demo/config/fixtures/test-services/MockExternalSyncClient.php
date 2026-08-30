<?php

declare(strict_types=1);

namespace App\Tests;

use App\Sync\ExternalSyncClientInterface;

final class MockExternalSyncClient implements ExternalSyncClientInterface
{
    public function register(object $request): void
    {
    }
}
