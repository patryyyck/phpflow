<?php

declare(strict_types=1);

use App\Event\EventInterface;
use App\Message\DeleteUser;
use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $frameworkConfig): void {
    $env = $_SERVER['APP_ENV'] ?? 'dev';
    $messengerConfig = $frameworkConfig->messenger();

    $messengerConfig->enabled(true)->defaultBus('query.bus');

    $messengerConfig->transport('async')->dsn('sqs://default');
    $messengerConfig->transport('mail')->dsn('sync://');

    $messengerConfig->routing(EventInterface::class)
        ->senders(['async']);

    if ('test' === $env) {
        $messengerConfig->transport('async')->dsn('sync://');
    }

    $messengerConfig->routing(DeleteUser::class)
        ->senders(['async', 'mail']);
};
