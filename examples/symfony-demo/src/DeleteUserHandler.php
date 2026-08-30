<?php

namespace App\MessageHandler;

use App\Message\DeleteUser;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class DeleteUserHandler
{
    #[AsMessageHandler]
    public function handle(DeleteUser $message): void
    {
    }
}
