<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Ast;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Infrastructure\Scanner\DirectoryScanner;
use PHPUnit\Framework\TestCase;

final class HttpResponseDetectionTest extends TestCase
{
    public function testItDetectsExplicitAndDefaultHttpResponseStatuses(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $responses = [];
        foreach ($analysis->httpResponses() as $response) {
            if (str_starts_with($response->source(), 'App\\Controller\\HttpResponseController::')) {
                $responses[$response->source()] = $response;
            }
        }

        self::assertCount(4, $responses);

        self::assertSame(
            201,
            $responses['App\\Controller\\HttpResponseController::created']->statusCode(),
        );
        self::assertSame(
            'JsonResponse',
            $responses['App\\Controller\\HttpResponseController::created']->responseType(),
        );

        self::assertSame(
            204,
            $responses['App\\Controller\\HttpResponseController::empty']->statusCode(),
        );
        self::assertSame(
            200,
            $responses['App\\Controller\\HttpResponseController::defaultStatus']->statusCode(),
        );
        self::assertSame(
            302,
            $responses['App\\Controller\\HttpResponseController::redirect']->statusCode(),
        );
    }

    public function testHttpResponsesAreNotDuplicatedAsGenericObjectReturns(): void
    {
        $project = (new DirectoryScanner())->scan(__DIR__.'/../Fixtures/SimpleProject');
        $analysis = (new ProjectAstAnalyzer())->analyze($project);

        $duplicates = array_values(array_filter(
            $analysis->methodReturns(),
            static fn ($return): bool =>
                str_starts_with($return->source(), 'App\\Controller\\HttpResponseController::'),
        ));

        self::assertCount(0, $duplicates);
    }
}
