<?php

declare(strict_types=1);

use App\ServiceCycle\CyclicServiceA;
use App\Tests\MockCyclicService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->alias(
        CyclicServiceA::class,
        MockCyclicService::class,
    );
};
