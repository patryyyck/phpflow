<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Ast\ProjectIndex;
use PhpFlow\Domain\Analysis\MessageRouting;

final class ResolveMessageRouting
{
    /**
     * @param list<MessageRouting> $routings
     * @return list<string>
     */
    public function transportsFor(
        string $message,
        array $routings,
        ProjectIndex $index,
    ): array {
        $transports = [];

        foreach ($routings as $routing) {
            if (
                $routing->message() === $message
                || $index->implements($message, $routing->message())
            ) {
                foreach ($routing->transports() as $transport) {
                    $transports[$transport] = true;
                }
            }
        }

        return array_keys($transports);
    }
}
