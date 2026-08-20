<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class EarlyReturnController
{
    #[Route('/early-return', methods: ['GET'])]
    public function run(bool $valid): JsonResponse
    {
        if (!$valid) {
            return new JsonResponse(['error' => 'invalid'], 400);
        }

        return new JsonResponse(['ok' => true], 200);
    }
}
