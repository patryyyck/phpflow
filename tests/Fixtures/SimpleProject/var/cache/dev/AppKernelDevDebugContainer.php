<?php

declare(strict_types=1);

namespace ContainerFixture;

use App\CompanyRepository;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stands in for a Symfony generated container dump under var/cache.
 *
 * Generated code is not application source: it must never contribute routes,
 * services or effects to the flow graph.
 */
class AppKernelDevDebugContainer
{
    #[Route(path: '/generated/must-not-be-scanned', methods: ['GET'])]
    public function generatedRoute(CompanyRepository $repository): void
    {
        $repository->findAll();
    }
}
