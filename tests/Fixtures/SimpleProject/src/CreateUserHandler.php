<?php

namespace App\MessageHandler;

use App\Message\CreateUser;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateUserHandler
{
    public function __invoke(CreateUser $message): void
    {
    }
}
