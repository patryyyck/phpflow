<?php

declare(strict_types=1);

namespace PhpFlow\Tests;

use PhpFlow\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testV010ReleaseVersionIsFrozen(): void
    {
        self::assertSame('0.1.0', Version::VERSION);
    }
}
