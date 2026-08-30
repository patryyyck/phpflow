<?php

declare(strict_types=1);

use App\Sync\ExternalSyncClient;
use App\Sync\ExternalSyncClientInterface;
use App\ServiceCycle\CyclicServiceA;
use App\ServiceCycle\CyclicServiceB;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire(true)
        ->autoconfigure(true);

    $services->load('App\\', __DIR__.'/../src/');

    $services->alias(
        ExternalSyncClientInterface::class,
        ExternalSyncClient::class,
    );

    $services->alias(
        CyclicServiceA::class,
        CyclicServiceB::class,
    );
};
