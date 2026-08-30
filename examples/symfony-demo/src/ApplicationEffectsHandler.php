<?php

declare(strict_types=1);

namespace App\Effects;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\MailerInterface;

final readonly class ApplicationEffectsHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private Filesystem $filesystem,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function run(object $email): void
    {
        $this->mailer->send($email);
        $this->filesystem->dumpFile('/tmp/export.csv', 'content');
        $this->cache->deleteItem('company.42');
    }
}
