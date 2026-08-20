<?php

namespace App\Message;

final readonly class CreateUser
{
    public function __construct(public string $email)
    {
    }
}
