<?php

declare(strict_types=1);

namespace App\ExceptionFlow;

final class DomainProblem extends \RuntimeException {}
final class InvalidResult extends \UnexpectedValueException {}

final class ExceptionFlowHandler
{
    public function nested(bool $valid, bool $enabled): void
    {
        if ($valid) {
            if ($enabled) {
                throw new DomainProblem('Nested');
            }
        }
    }

    public function run(bool $valid, array $items): void
    {
        if (!$valid) {
            throw new DomainProblem('Invalid domain state');
        }

        count($items);

        if ($items === []) {
            throw new InvalidResult('No result');
        }
    }
}
