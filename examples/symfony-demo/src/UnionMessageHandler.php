<?php

declare(strict_types=1);

namespace App\UnionHandler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class FirstCommand {}
final class SecondCommand {}

#[AsMessageHandler]
final class UnionCommandHandler
{
    public function __invoke(FirstCommand|SecondCommand $command): void
    {
    }
}
