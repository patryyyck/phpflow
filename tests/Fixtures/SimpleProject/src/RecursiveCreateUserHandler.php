<?php

namespace App\MessageHandler;

use App\Event\UserCreated;
use App\Message\CreateUser;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class RecursiveCreateUserHandler
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function __invoke(CreateUser $message): void
    {
        $this->bus->dispatch(new UserCreated());
    }
}
