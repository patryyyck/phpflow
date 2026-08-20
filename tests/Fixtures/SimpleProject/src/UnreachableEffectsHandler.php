<?php

declare(strict_types=1);

namespace App\Unreachable;

interface UnreachableRepositoryInterface
{
    public function reachable(string $value): void;
    public function unreachable(string $value): void;
}

final readonly class UnreachableResult {}

final readonly class UnreachableEffectsHandler
{
    public function __construct(
        private UnreachableRepositoryInterface $repository,
    ) {
    }

    public function afterReturn(): UnreachableResult
    {
        return new UnreachableResult();

        $this->repository->unreachable('after-return');
    }

    public function afterThrow(bool $fail): void
    {
        if ($fail) {
            throw new \RuntimeException('boom');

            $this->repository->unreachable('after-throw');
        }

        $this->repository->reachable('outside-if');
    }

    public function afterContinue(array $items): void
    {
        foreach ($items as $item) {
            continue;

            $this->repository->unreachable('after-continue');
        }
    }

    public function afterBreak(array $items): void
    {
        foreach ($items as $item) {
            break;

            $this->repository->unreachable('after-break');
        }

        $this->repository->reachable('after-loop');
    }

    public function afterCompleteIfElse(bool $flag): void
    {
        if ($flag) {
            return;
        } else {
            throw new \RuntimeException('stop');
        }

        $this->repository->unreachable('after-complete-if-else');
    }

    public function afterCompleteElseIf(string $state): void
    {
        if ($state === 'a') {
            return;
        } elseif ($state === 'b') {
            throw new \RuntimeException('b');
        } else {
            return;
        }

        $this->repository->unreachable('after-complete-elseif');
    }

    public function afterPartialIfElse(bool $flag): void
    {
        if ($flag) {
            return;
        } else {
            $this->repository->reachable('else-continues');
        }

        $this->repository->reachable('after-partial-if-else');
    }


    public function afterCompleteTryCatch(bool $fail): void
    {
        try {
            if ($fail) {
                throw new \RuntimeException('fail');
            }

            return;
        } catch (\RuntimeException $exception) {
            throw $exception;
        }

        $this->repository->unreachable('after-complete-try-catch');
    }

    public function afterPartialTryCatch(bool $fail): void
    {
        try {
            if ($fail) {
                throw new \RuntimeException('fail');
            }

            return;
        } catch (\RuntimeException $exception) {
            $this->repository->reachable('catch-continues');
        }

        $this->repository->reachable('after-partial-try-catch');
    }

    public function afterTerminatingFinally(bool $fail): void
    {
        try {
            if ($fail) {
                $this->repository->reachable('inside-try');
            }
        } finally {
            return;
        }

        $this->repository->unreachable('after-terminating-finally');
    }


    public function afterTerminatingMatch(string $state): void
    {
        match ($state) {
            'a' => throw new \RuntimeException('a'),
            'b' => throw new \LogicException('b'),
            default => throw new \UnexpectedValueException('default'),
        };

        $this->repository->unreachable('after-terminating-match');
    }

    public function afterPartialMatch(string $state): void
    {
        match ($state) {
            'a' => throw new \RuntimeException('a'),
            default => $this->repository->reachable('default-continues'),
        };

        $this->repository->reachable('after-partial-match');
    }

    public function afterNonExhaustiveTerminatingMatch(string $state): void
    {
        match ($state) {
            'a' => throw new \RuntimeException('a'),
            'b' => throw new \LogicException('b'),
        };

        $this->repository->reachable('after-non-exhaustive-match');
    }

}
