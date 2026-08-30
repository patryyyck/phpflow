<?php

declare(strict_types=1);

namespace App\Preferred;

interface AuthenticatorInterface
{
    public function token(): string;
}

final class ProductionAuthenticator implements AuthenticatorInterface
{
    public function token(): string
    {
        return 'production';
    }
}

final readonly class AuthenticatedClient
{
    public function __construct(
        private AuthenticatorInterface $authenticator,
    ) {
    }

    public function run(): string
    {
        return $this->authenticator->token();
    }
}
