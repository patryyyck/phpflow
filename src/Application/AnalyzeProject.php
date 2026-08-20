<?php
declare(strict_types=1);
namespace PhpFlow\Application;

use PhpFlow\Ast\ProjectAstAnalyzer;
use PhpFlow\Ast\ProjectIndexer;
use PhpFlow\Domain\Analysis\ResolvedMessageRouting;
use PhpFlow\Domain\Analysis\ProjectAnalysis;
use PhpFlow\Domain\Project;
use PhpFlow\Infrastructure\Messenger\MessengerRoutingReader;
use PhpFlow\Infrastructure\Symfony\SymfonyServiceAliasReader;
use PhpFlow\Domain\Analysis\ServiceCall;
use PhpFlow\Domain\Analysis\RepositoryCall;

final readonly class AnalyzeProject
{
    public function __construct(
        private ProjectAstAnalyzer $astAnalyzer,
        private MessengerRoutingReader $routingReader,
        private ProjectIndexer $indexer = new ProjectIndexer(),
        private ResolveMessageRouting $routingResolver = new ResolveMessageRouting(),
        private SymfonyServiceAliasReader $serviceAliasReader = new SymfonyServiceAliasReader(),
    ) {}

    public function analyze(Project $project): ProjectAnalysis
    {
        $ast = $this->astAnalyzer->analyze($project);
        $routings = $this->routingReader->read($project->path());
        $index = $this->indexer->index($project);

        $resolved = [];
        $seenMessages = [];

        foreach ($ast->messageDispatches() as $dispatch) {
            $message = $dispatch->message();

            if (isset($seenMessages[$message])) {
                continue;
            }

            $seenMessages[$message] = true;
            $transports = $this->routingResolver->transportsFor(
                $message,
                $routings,
                $index,
            );

            if ($transports !== []) {
                $resolved[] = new ResolvedMessageRouting(
                    $message,
                    $transports,
                );
            }
        }

        $aliases = $this->serviceAliasReader->read($project->path());

        $repositoryCalls = [];

        foreach ($ast->repositoryCalls() as $call) {
            $implementation = $aliases[$call->repository()]
                ?? $index->uniqueImplementationOf($call->repository());

            if (
                $implementation === null
                && $index->hasSymbol($call->repository())
                && !$index->isInterface($call->repository())
            ) {
                $implementation = $call->repository();
            }

            $repositoryCalls[] = new RepositoryCall(
                $call->source(),
                $call->repository(),
                $call->method(),
                $call->position(),
                $implementation,
            );
        }

        $serviceCalls = [];

        foreach ($ast->serviceCalls() as $call) {
            $implementation = $aliases[$call->service()]
                ?? $call->implementation()
                ?? $index->uniqueImplementationOf($call->service());

            if (
                $implementation === null
                && $index->hasSymbol($call->service())
                && !$index->isInterface($call->service())
            ) {
                $implementation = $call->service();
            }

            $serviceCalls[] = new ServiceCall(
                $call->source(),
                $call->service(),
                $call->method(),
                $implementation,
                $call->position(),
            );
        }

        return new ProjectAnalysis(
            $ast->statistics(),
            $ast->attributes(),
            $ast->routes(),
            $ast->messageDispatches(),
            $ast->unresolvedCalls(),
            $ast->messageHandlers(),
            $routings,
            $resolved,
            $this->routingReader->transports($project->path()),
            $repositoryCalls,
            $ast->httpCalls(),
            $serviceCalls,
            $ast->databaseEffects(),
            $ast->applicationEffects(),
            $ast->thrownExceptions(),
            $ast->methodReturns(),
            $ast->httpResponses(),
            $ast->guardClauses(),
            $ast->controlBranches(),
            $ast->loopControls(),
        );
    }
}
