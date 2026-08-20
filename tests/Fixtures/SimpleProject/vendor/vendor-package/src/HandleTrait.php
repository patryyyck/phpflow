<?php

namespace VendorPackage;

trait HandleTrait
{
    private function handle(object $message, array $stamps = []): mixed
    {
        return $this->messageBus->dispatch($message, $stamps);
    }
}
