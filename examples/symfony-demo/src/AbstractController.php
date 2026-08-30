<?php

namespace App\Controller;

use Symfony\Component\Messenger\MessageBusInterface;

abstract class AbstractController
{
    public function __construct(protected MessageBusInterface $queryBus)
    {
    }

    protected function handle(object $query): mixed
    {
        return $this->queryBus->dispatch($query);
    }
}
