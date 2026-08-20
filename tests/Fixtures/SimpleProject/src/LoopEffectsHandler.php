<?php

declare(strict_types=1);

namespace App\Loops;

interface LoopRepositoryInterface
{
    public function record(string $value): void;
}

interface LoopClientInterface
{
    public function send(string $value): void;
}

final readonly class LoopEffectsHandler
{
    public function __construct(
        private LoopRepositoryInterface $repository,
        private LoopClientInterface $client,
    ) {
    }

    public function foreachLoop(array $items): void
    {
        foreach ($items as $key => $item) {
            if ($item === 'send') {
                $this->client->send($item);
            }

            $this->repository->record($item);
        }

        $this->repository->record('after-foreach');
    }

    public function forLoop(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->repository->record((string) $i);
        }
    }

    public function whileLoop(bool $running): void
    {
        while ($running) {
            $this->client->send('while');
            $running = false;
        }
    }

    public function doWhileLoop(bool $running): void
    {
        do {
            $this->client->send('do');
            $running = false;
        } while ($running);
    }

    public function controlFlow(array $items): void
    {
        foreach ($items as $item) {
            if ($item === 'skip') {
                continue;
            }

            if ($item === 'stop') {
                break;
            }

            $this->client->send($item);
        }
    }

    public function nestedBreak(array $groups): void
    {
        foreach ($groups as $items) {
            foreach ($items as $item) {
                if ($item === 'stop-all') {
                    break 2;
                }
            }
        }
    }

}
