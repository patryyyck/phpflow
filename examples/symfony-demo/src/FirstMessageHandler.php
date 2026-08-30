<?php

namespace App\Recursive;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class FirstMessageHandler
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function __invoke(FirstMessage $message): void
    {
        $this->bus->dispatch(new SecondMessage());
    }
}
