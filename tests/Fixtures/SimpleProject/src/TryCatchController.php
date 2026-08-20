<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TryCatchProblem extends \RuntimeException {}

interface CleanupServiceInterface
{
    public function cleanup(): void;
}

final readonly class TryCatchController
{
    public function __construct(
        private CleanupServiceInterface $cleanupService,
    ) {
    }

    #[Route('/try-catch', methods: ['GET'])]
    public function run(bool $fail): JsonResponse
    {
        try {
            if ($fail) {
                throw new TryCatchProblem('boom');
            }

            return new JsonResponse(['ok' => true], 200);
        } catch (TryCatchProblem $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\Throwable $exception) {
            return new JsonResponse(['error' => 'unexpected'], 500);
        } finally {
            $this->cleanupService->cleanup();
        }
    }
}
