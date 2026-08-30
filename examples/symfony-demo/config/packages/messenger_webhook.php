<?php
declare(strict_types=1);
use App\Event\EventInterface;
use Symfony\Config\FrameworkConfig;
return static function (FrameworkConfig $frameworkConfig): void {
    $messenger = $frameworkConfig->messenger();
    $messenger->transport('webhook_event')->dsn('sqs://default');
    $messenger->routing(EventInterface::class)->senders(['webhook_event']);
};
