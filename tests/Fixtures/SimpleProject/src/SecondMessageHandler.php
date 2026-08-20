<?php

namespace App\Recursive;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class SecondMessageHandler
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function __invoke(SecondMessage $message): void
    {
        $this->bus->dispatch(new ThirdMessage());
    }
}
