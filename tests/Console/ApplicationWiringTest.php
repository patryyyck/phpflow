<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use PhpFlow\Console\Application;
use PHPUnit\Framework\TestCase;

final class ApplicationWiringTest extends TestCase
{
    public function testItCanBeConstructedWithAllCommandDependencies(): void
    {
        self::assertInstanceOf(Application::class, new Application());
    }
}
