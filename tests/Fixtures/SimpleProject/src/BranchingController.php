<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class BranchResult {}

final class BranchingController
{
    #[Route('/branches/{state}', methods: ['GET'])]
    public function branches(string $state): JsonResponse
    {
        if ($state === 'created') {
            return new JsonResponse(['state' => $state], 201);
        } elseif ($state === 'accepted') {
            return new JsonResponse(['state' => $state], 202);
        } else {
            return new JsonResponse(['state' => $state], 200);
        }
    }

    public function result(string $state): BranchResult
    {
        return match ($state) {
            'a' => new BranchResult(),
            'b', 'c' => new BranchResult(),
            default => new BranchResult(),
        };
    }

    public function nestedMatch(bool $enabled, string $state): BranchResult
    {
        if ($enabled) {
            return match ($state) {
                'a' => new BranchResult(),
                default => new BranchResult(),
            };
        }

        return new BranchResult();
    }
}
