<?php

namespace App\Controller;

use App\Recursive\FirstMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RecursiveController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/recursive', methods: ['POST'])]
    public function run(): void
    {
        $this->bus->dispatch(new FirstMessage());
    }
}
