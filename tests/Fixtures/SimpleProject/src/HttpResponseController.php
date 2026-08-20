<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HttpResponseController
{
    #[Route('/responses/created', methods: ['POST'])]
    public function created(): JsonResponse
    {
        return new JsonResponse(
            ['id' => 42],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/responses/empty', methods: ['DELETE'])]
    public function empty(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route('/responses/default', methods: ['GET'])]
    public function defaultStatus(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/responses/redirect', methods: ['GET'])]
    public function redirect(): RedirectResponse
    {
        return new RedirectResponse('/target');
    }
}
