<?php

namespace App\Recursive;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ThirdMessageHandler
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function __invoke(ThirdMessage $message): void
    {
        $this->bus->dispatch(new FirstMessage());
    }
}
