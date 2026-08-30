<?php

namespace App\Query;

final readonly class ListCompanies
{
    public function __construct(public string $userId)
    {
    }
}
