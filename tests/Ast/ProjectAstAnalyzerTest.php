<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class ProjectAstAnalyzerTest extends TestCase
{
    private function analyze(): \PhpFlow\Domain\Analysis\ProjectAnalysis
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');

        return (new ProjectAstAnalyzer())->analyze($project);
    }

    public function testItCountsPhpDeclarationsAndAttributes(): void
    {
        $analysis = $this->analyze();
        $statistics = $analysis->statistics();

        self::assertSame(59, $statistics->classes());
        self::assertSame(16, $statistics->interfaces());
        self::assertSame(1, $statistics->traits());
        self::assertSame(1, $statistics->enums());
        self::assertCount(24, $analysis->attributes());
    }

    public function testItDetectsSymfonyRouteAttributes(): void
    {
        $analysis = $this->analyze();

        self::assertCount(14, $analysis->routes());

        $bySignature = [];

        foreach ($analysis->routes() as $route) {
            $methods = $route->methods() === [] ? ['*'] : $route->methods();

            foreach ($methods as $method) {
                $bySignature[$method.' '.($route->path() ?? '<dynamic>')] = $route;
            }
        }

        $list = $bySignature['GET /users'];
        self::assertSame(['GET'], $list->methods());
        self::assertSame('App\\Controller\\UserController::list', $list->controller());
        self::assertSame('user_list', $list->name());

        $show = $bySignature['GET /users/{id}'];
        self::assertSame(['GET', 'HEAD'], $show->methods());
    }

    public function testItDetectsMessengerDispatchWithANewMessage(): void
    {
        $analysis = $this->analyze();

        $dispatches = array_values(array_filter(
            $analysis->messageDispatches(),
            static fn ($dispatch): bool => $dispatch->source() === 'App\\Controller\\UserController::create',
        ));

        self::assertCount(1, $dispatches);
        self::assertSame('App\\Message\\CreateUser', $dispatches[0]->message());
    }

    public function testItResolvesAMessageStoredInALocalVariable(): void
    {
        $analysis = $this->analyze();

        $dispatches = array_values(array_filter(
            $analysis->messageDispatches(),
            static fn ($dispatch): bool => $dispatch->source() === 'App\\Controller\\UserController::preRegister',
        ));

        self::assertCount(1, $dispatches);
        self::assertSame('App\\Command\\PreRegisterCompanies', $dispatches[0]->message());
    }

    public function testItResolvesAnInheritedWrapperThatDispatchesItsParameter(): void
    {
        $analysis = $this->analyze();

        $dispatches = array_values(array_filter(
            $analysis->messageDispatches(),
            static fn ($dispatch): bool => $dispatch->source() === 'App\\Controller\\UserController::companies',
        ));

        self::assertCount(1, $dispatches);
        self::assertSame('App\\Query\\ListCompanies', $dispatches[0]->message());
    }

    public function testItUsesVendorAsAResolutionSourceWithoutAnalyzingItAsApplicationCode(): void
    {
        $analysis = $this->analyze();

        $dispatches = array_values(array_filter(
            $analysis->messageDispatches(),
            static fn ($dispatch): bool => $dispatch->source() === 'App\\Controller\\VendorBasedController::run',
        ));

        self::assertCount(1, $dispatches);
        self::assertSame('App\\Message\\ExternalMessage', $dispatches[0]->message());
    }

    public function testItResolvesADispatchWrapperProvidedByAnExternalTrait(): void
    {
        $analysis = $this->analyze();

        $dispatches = array_values(array_filter(
            $analysis->messageDispatches(),
            static fn ($dispatch): bool => $dispatch->source() === 'App\\Controller\\TraitBasedController::run',
        ));

        self::assertCount(1, $dispatches);
        self::assertSame('App\\Message\\TraitMessage', $dispatches[0]->message());
    }


    public function testItDetectsSymfonyMessengerHandlers(): void
    {
        $analysis = $this->analyze();

        $handlers = array_values(array_filter(
            $analysis->messageHandlers(),
            static fn ($handler): bool => $handler->message() === 'App\\Message\\CreateUser',
        ));

        self::assertCount(2, $handlers);

        $names = array_map(static fn ($handler): string => $handler->handler(), $handlers);

        self::assertContains('App\\MessageHandler\\CreateUserHandler::__invoke', $names);
        self::assertContains('App\\MessageHandler\\RecursiveCreateUserHandler::__invoke', $names);
    }

    public function testAHandlerCanDispatchAnotherMessage(): void
    {
        $analysis = $this->analyze();

        $dispatches = array_values(array_filter(
            $analysis->messageDispatches(),
            static fn ($dispatch): bool =>
                $dispatch->source() === 'App\\MessageHandler\\RecursiveCreateUserHandler::__invoke',
        ));

        self::assertCount(1, $dispatches);
        self::assertSame('App\\Event\\UserCreated', $dispatches[0]->message());
    }


    public function testItDetectsMethodLevelMessengerHandlers(): void
    {
        $analysis = $this->analyze();

        $handlers = array_values(array_filter(
            $analysis->messageHandlers(),
            static fn ($handler): bool =>
                $handler->message() === 'App\\Message\\DeleteUser'
                && $handler->handler() === 'App\\MessageHandler\\DeleteUserHandler::handle',
        ));

        self::assertCount(1, $handlers);
        self::assertSame(
            'App\\MessageHandler\\DeleteUserHandler::handle',
            $handlers[0]->handler(),
        );
    }

}
