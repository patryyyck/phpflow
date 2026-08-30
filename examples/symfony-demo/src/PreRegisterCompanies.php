<?php

namespace App\Command;

final readonly class PreRegisterCompanies
{
    public function __construct(public string $userId)
    {
    }
}
