<?php

namespace VendorPackage;

use Symfony\Component\Messenger\MessageBusInterface;

abstract class ExternalController
{
    public function __construct(protected MessageBusInterface $bus)
    {
    }

    protected function execute(object $message): mixed
    {
        return $this->bus->dispatch($message);
    }
}
