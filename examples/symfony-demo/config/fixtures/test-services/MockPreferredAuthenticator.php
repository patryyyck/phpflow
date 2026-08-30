<?php

declare(strict_types=1);

namespace App\Tests;

use App\Preferred\AuthenticatorInterface;

final class MockPreferredAuthenticator implements AuthenticatorInterface
{
    public function token(): string
    {
        return 'mock';
    }
}
