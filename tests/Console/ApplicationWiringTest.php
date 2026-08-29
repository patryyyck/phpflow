<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Console;

use PhpFlow\Console\Application;
use PhpFlow\Version;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Application as SymfonyApplication;

final class ApplicationWiringTest extends TestCase
{
    public function testItCanBeConstructedWithAllCommandDependencies(): void
    {
        self::assertInstanceOf(Application::class, new Application());
    }

    public function testItExposesTheReleaseNameAndVersion(): void
    {
        $application = $this->symfonyApplication();

        self::assertSame('PHPFlow', $application->getName());
        self::assertSame(Version::VERSION, $application->getVersion());
        self::assertSame('0.1.0', $application->getVersion());
    }

    public function testPublicCommandNamesAreStableForV010(): void
    {
        $application = $this->symfonyApplication();

        $expectedCommands = [
            'diff',
            'export:html',
            'export:json',
            'export:mermaid',
            'impact',
            'impact:exception',
            'impact:http',
            'impact:message',
            'impact:service',
            'impact:table',
            'inspect',
            'scan',
        ];

        foreach ($expectedCommands as $commandName) {
            self::assertTrue(
                $application->has($commandName),
                sprintf('Expected public command "%s" to be registered.', $commandName),
            );
        }
    }

    public function testEveryPublicCommandHasAHelpDescription(): void
    {
        $application = $this->symfonyApplication();

        foreach ($application->all() as $name => $command) {
            if (in_array($name, ['help', 'list', 'completion', '_complete'], true)) {
                continue;
            }

            self::assertNotSame(
                '',
                trim($command->getDescription()),
                sprintf('Command "%s" must have a description for --help.', $name),
            );
        }
    }

    private function symfonyApplication(): SymfonyApplication
    {
        $phpFlowApplication = new Application();
        $property = new ReflectionProperty(Application::class, 'application');

        $application = $property->getValue($phpFlowApplication);

        self::assertInstanceOf(SymfonyApplication::class, $application);

        return $application;
    }
}
