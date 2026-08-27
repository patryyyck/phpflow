<?php

declare(strict_types=1);

namespace PhpFlow\Application;

use PhpFlow\Domain\Analysis\ProjectAnalysis;
use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;

final class BuildFlowGraph
{
    public function build(ProjectAnalysis $analysis): Graph
    {
        $graph = new Graph();
        $graph->setSymbolFiles($analysis->symbolFiles());

        foreach ($analysis->routes() as $route) {
            $routeId = $this->routeId($route->methods(), $route->path());
            $controllerId = 'controller:'.$route->controller();

            $graph->addNode(new Node(
                $routeId,
                NodeType::ROUTE,
                $this->routeLabel($route->methods(), $route->path()),
            ));

            $graph->addNode(new Node(
                $controllerId,
                NodeType::CONTROLLER,
                $route->controller(),
            ));

            $graph->addEdge(new Edge(
                $routeId,
                $controllerId,
                EdgeType::INVOKES,
            ));
        }

        $routing = [];

        foreach ($analysis->resolvedMessageRoutings() as $messageRouting) {
            $routing[$messageRouting->message()] = $messageRouting->transports();
        }

        foreach ($analysis->messageRoutings() as $messageRouting) {
            $routing[$messageRouting->message()] ??= $messageRouting->transports();
        }

        $knownHandlers = [];
        foreach ($analysis->messageHandlers() as $handler) {
            $knownHandlers[$handler->handler()] = true;
        }

        /** @var array<string, string> $repositorySourceNodes */
        $repositorySourceNodes = [];

        foreach ($analysis->repositoryCalls() as $call) {
            if ($call->implementation() === null) {
                continue;
            }

            $implementationMethod = $call->implementation().'::'.$call->method();

            $repositorySourceNodes[$implementationMethod] =
                $call->implementation() === $call->repository()
                    ? 'repository:'.$implementationMethod
                    : 'repository_impl:'.$implementationMethod;
        }

        /** @var array<string, string> $serviceSourceNodes */
        $serviceSourceNodes = [];

        foreach ($analysis->serviceCalls() as $call) {
            if ($call->implementation() === null) {
                continue;
            }

            $implementationMethod = $call->implementation().'::'.$call->method();

            $serviceSourceNodes[$implementationMethod] =
                $call->implementation() === $call->service()
                    ? 'service:'.$implementationMethod
                    : 'service_impl:'.$implementationMethod;
        }

        foreach ($analysis->messageDispatches() as $dispatch) {
            [$sourceId, $sourceType] = $this->sourceNode(
                $dispatch->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );
            $messageId = 'message:'.$dispatch->message();

            $graph->addNode(new Node($sourceId, $sourceType, $dispatch->source()));
            $graph->addNode(new Node($messageId, NodeType::MESSAGE, $dispatch->message()));

            $transports = $routing[$dispatch->message()] ?? [];
            $label = $transports === []
                ? 'sync'
                : 'async: '.implode(', ', $transports);

            $graph->addEdge(new Edge(
                $sourceId,
                $messageId,
                EdgeType::DISPATCHES,
                $label,
                $dispatch->position()?->filePosition(),
            ));
        }

        foreach ($analysis->messageHandlers() as $handler) {
            $messageId = 'message:'.$handler->message();
            $handlerId = 'handler:'.$handler->handler();

            $graph->addNode(new Node($messageId, NodeType::MESSAGE, $handler->message()));
            $graph->addNode(new Node($handlerId, NodeType::HANDLER, $handler->handler()));
            $graph->addEdge(new Edge(
                $messageId,
                $handlerId,
                EdgeType::HANDLED_BY,
            ));
        }

        foreach ($analysis->repositoryCalls() as $call) {
            [$sourceId, $sourceType] = $this->sourceNode(
                $call->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );
            $repositoryCallable = $call->repository().'::'.$call->method();
            $repositoryId = 'repository:'.$repositoryCallable;

            $graph->addNode(new Node($sourceId, $sourceType, $call->source()));
            $graph->addNode(new Node(
                $repositoryId,
                NodeType::REPOSITORY,
                $repositoryCallable,
            ));
            $graph->addEdge(new Edge(
                $sourceId,
                $repositoryId,
                EdgeType::CALLS,
                'repository',
                $call->position()?->filePosition(),
            ));

            if ($call->implementation() !== null && $call->implementation() !== $call->repository()) {
                $implementationCallable = $call->implementation().'::'.$call->method();
                $implementationId = 'repository_impl:'.$implementationCallable;

                $graph->addNode(new Node(
                    $implementationId,
                    NodeType::REPOSITORY,
                    $implementationCallable,
                ));
                $graph->addEdge(new Edge(
                    $repositoryId,
                    $implementationId,
                    EdgeType::CALLS,
                    'resolves_to',
                ));
            }
        }

        foreach ($analysis->httpCalls() as $call) {
            [$sourceId, $sourceType] = $this->sourceNode(
                $call->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );

            $endpointLabel = trim(sprintf(
                '%s %s',
                $call->method() ?? 'HTTP',
                $call->url() ?? '<dynamic URL>',
            ));

            $endpointId = 'http:'.hash(
                'sha256',
                implode('|', [
                    $call->client(),
                    $call->method() ?? '',
                    $call->url() ?? '<dynamic>',
                ]),
            );

            $graph->addNode(new Node($sourceId, $sourceType, $call->source()));
            $graph->addNode(new Node(
                $endpointId,
                NodeType::HTTP_ENDPOINT,
                $endpointLabel,
            ));
            $graph->addEdge(new Edge(
                $sourceId,
                $endpointId,
                EdgeType::CALLS,
                'http',
                $call->position()?->filePosition(),
            ));
        }

        foreach ($analysis->databaseEffects() as $effect) {
            [$sourceId, $sourceType] = $this->sourceNode(
                $effect->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );

            $label = trim(
                $effect->operation().' '.($effect->target() ?? '<unknown>'),
            );

            $databaseId = 'database:'.hash(
                'sha256',
                implode('|', [
                    $effect->source(),
                    $effect->operation(),
                    $effect->target() ?? '',
                    $effect->sql() ?? '',
                ]),
            );

            $graph->addNode(new Node($sourceId, $sourceType, $effect->source()));
            $graph->addNode(new Node($databaseId, NodeType::DATABASE, $label));
            $graph->addEdge(new Edge(
                $sourceId,
                $databaseId,
                EdgeType::CALLS,
                'database',
                $effect->position()?->filePosition(),
            ));
        }

        foreach ($analysis->applicationEffects() as $effect) {
            [$sourceId, $sourceType] = $this->sourceNode(
                $effect->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );

            $nodeType = match ($effect->kind()) {
                'mail' => NodeType::MAIL,
                'filesystem' => NodeType::FILESYSTEM,
                'cache' => NodeType::CACHE,
                default => NodeType::SERVICE,
            };

            $label = trim(
                $effect->operation().' '.($effect->target() ?? ''),
            );

            $effectId = 'effect:'.hash(
                'sha256',
                implode('|', [
                    $effect->source(),
                    $effect->kind(),
                    $effect->operation(),
                    $effect->target() ?? '',
                ]),
            );

            $graph->addNode(new Node($sourceId, $sourceType, $effect->source()));
            $graph->addNode(new Node($effectId, $nodeType, $label));
            $graph->addEdge(new Edge(
                $sourceId,
                $effectId,
                EdgeType::CALLS,
                $effect->kind(),
                $effect->position()?->filePosition(),
            ));
        }

        foreach ($analysis->thrownExceptions() as $exception) {
            [$sourceId, $sourceType] = $this->sourceNode(
                $exception->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );

            $exceptionId = 'exception:'.hash(
                'sha256',
                $exception->source().'|'.$exception->exception().'|'.($exception->position()?->filePosition() ?? ''),
            );

            $graph->addNode(new Node($sourceId, $sourceType, $exception->source()));
            $graph->addNode(new Node(
                $exceptionId,
                NodeType::EXCEPTION,
                'throws '.$exception->exception(),
            ));

            if ($exception->condition() === null) {
                $graph->addEdge(new Edge(
                    $sourceId,
                    $exceptionId,
                    EdgeType::CALLS,
                    'throws',
                    $exception->position()?->filePosition(),
                ));

                continue;
            }

            $conditionId = 'condition:'.hash(
                'sha256',
                $exception->source().'|'.$exception->condition().'|'.($exception->position()?->filePosition() ?? ''),
            );

            $graph->addNode(new Node(
                $conditionId,
                NodeType::CONDITION,
                $exception->condition(),
            ));
            $graph->addEdge(new Edge(
                $sourceId,
                $conditionId,
                EdgeType::CALLS,
                'condition',
                $exception->position()?->filePosition(),
            ));
            $graph->addEdge(new Edge(
                $conditionId,
                $exceptionId,
                EdgeType::CALLS,
                'throws',
            ));
        }

        foreach ($analysis->httpResponses() as $response) {
            [$sourceId, $sourceType] = $this->sourceNode(
                $response->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );

            $label = $response->statusCode() === null
                ? 'HTTP '.$response->responseType()
                : 'HTTP '.$response->statusCode().' '.$response->responseType();

            $responseId = 'http_response:'.hash(
                'sha256',
                $response->source().'|'.$response->responseType().'|'.($response->statusCode() ?? '?').'|'.($response->position()?->filePosition() ?? ''),
            );

            $graph->addNode(new Node($sourceId, $sourceType, $response->source()));
            $graph->addNode(new Node(
                $responseId,
                NodeType::HTTP_RESPONSE,
                $label,
            ));

            if ($response->branch() === null) {
                $graph->addEdge(new Edge(
                    $sourceId,
                    $responseId,
                    EdgeType::CALLS,
                    'responds',
                    $response->position()?->filePosition(),
                ));
            } else {
                $conditionId = 'condition:'.hash(
                    'sha256',
                    $response->source().'|'.$response->branch().'|'.($response->position()?->filePosition() ?? ''),
                );

                $graph->addNode(new Node(
                    $conditionId,
                    NodeType::CONDITION,
                    $response->branch(),
                ));
                $graph->addEdge(new Edge(
                    $sourceId,
                    $conditionId,
                    EdgeType::CALLS,
                    'branch',
                    $response->position()?->filePosition(),
                ));
                $graph->addEdge(new Edge(
                    $conditionId,
                    $responseId,
                    EdgeType::CALLS,
                    'responds',
                ));
            }
        }

        foreach ($analysis->methodReturns() as $return) {
            [$sourceId, $sourceType] = $this->sourceNode(
                $return->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );

            $returnId = 'return:'.hash(
                'sha256',
                $return->source().'|'.$return->type().'|'.($return->position()?->filePosition() ?? ''),
            );

            $graph->addNode(new Node($sourceId, $sourceType, $return->source()));
            $graph->addNode(new Node(
                $returnId,
                NodeType::RETURN_VALUE,
                'returns '.$return->type(),
            ));

            if ($return->branch() === null) {
                $graph->addEdge(new Edge(
                    $sourceId,
                    $returnId,
                    EdgeType::CALLS,
                    'returns',
                    $return->position()?->filePosition(),
                ));
            } else {
                $conditionId = 'condition:'.hash(
                    'sha256',
                    $return->source().'|'.$return->branch().'|'.($return->position()?->filePosition() ?? ''),
                );

                $graph->addNode(new Node(
                    $conditionId,
                    NodeType::CONDITION,
                    $return->branch(),
                ));
                $graph->addEdge(new Edge(
                    $sourceId,
                    $conditionId,
                    EdgeType::CALLS,
                    'branch',
                    $return->position()?->filePosition(),
                ));
                $graph->addEdge(new Edge(
                    $conditionId,
                    $returnId,
                    EdgeType::CALLS,
                    'returns',
                ));
            }
        }

        foreach ($analysis->serviceCalls() as $call) {
            [$sourceId, $sourceType] = $this->sourceNode(
                $call->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );

            $serviceMethod = $call->service().'::'.$call->method();
            $serviceId = 'service:'.$serviceMethod;

            $graph->addNode(new Node($sourceId, $sourceType, $call->source()));
            $graph->addNode(new Node($serviceId, NodeType::SERVICE, $serviceMethod));
            $graph->addEdge(new Edge(
                $sourceId,
                $serviceId,
                EdgeType::CALLS,
                'calls',
                $call->position()?->filePosition(),
                $call->arguments(),
            ));

            if ($call->implementation() !== null && $call->implementation() !== $call->service()) {
                $implementationMethod = $call->implementation().'::'.$call->method();
                $implementationId = 'service_impl:'.$implementationMethod;

                $graph->addNode(new Node(
                    $implementationId,
                    NodeType::SERVICE,
                    $implementationMethod,
                ));
                $graph->addEdge(new Edge(
                    $serviceId,
                    $implementationId,
                    EdgeType::CALLS,
                    'resolves_to',
                ));
            }
        }

        foreach ($analysis->loopControls() as $control) {
            [$sourceId, $sourceType] = $this->sourceNode(
                $control->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );

            $label = $control->level() === 1
                ? $control->operation()
                : $control->operation().' '.$control->level();

            $controlId = 'loop_control:'.hash(
                'sha256',
                $control->source().'|'.$label.'|'.($control->position()?->filePosition() ?? ''),
            );

            $graph->addNode(new Node($sourceId, $sourceType, $control->source()));
            $graph->addNode(new Node(
                $controlId,
                NodeType::LOOP_CONTROL,
                $label,
            ));

            if ($control->branch() === null) {
                $graph->addEdge(new Edge(
                    $sourceId,
                    $controlId,
                    EdgeType::CALLS,
                    strtolower($control->operation()),
                    $control->position()?->filePosition(),
                ));

                continue;
            }

            $conditionId = 'condition:'.hash(
                'sha256',
                $control->source().'|'.$control->branch().'|'.($control->position()?->filePosition() ?? ''),
            );

            $graph->addNode(new Node(
                $conditionId,
                NodeType::CONDITION,
                $control->branch(),
            ));
            $graph->addEdge(new Edge(
                $sourceId,
                $conditionId,
                EdgeType::CALLS,
                'branch',
                $control->position()?->filePosition(),
            ));
            $graph->addEdge(new Edge(
                $conditionId,
                $controlId,
                EdgeType::CALLS,
                strtolower($control->operation()),
            ));
        }

        $this->applyGuardContinuations(
            $graph,
            $analysis,
            $knownHandlers,
            $repositorySourceNodes,
            $serviceSourceNodes,
        );

        $this->applyControlBranches(
            $graph,
            $analysis,
            $knownHandlers,
            $repositorySourceNodes,
            $serviceSourceNodes,
        );

        return $graph;
    }

    /**
     * @param array<string, true> $knownHandlers
     * @param array<string, string> $repositorySourceNodes
     * @param array<string, string> $serviceSourceNodes
     */
    private function applyControlBranches(
        Graph $graph,
        ProjectAnalysis $analysis,
        array $knownHandlers,
        array $repositorySourceNodes,
        array $serviceSourceNodes,
    ): void {
        foreach ($analysis->controlBranches() as $branch) {
            [$sourceId] = $this->sourceNode(
                $branch->source(),
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );

            $branchId = 'control_branch:'.hash(
                'sha256',
                $branch->source().'|'.$branch->label().'|'.$branch->startFilePos().'|'.$branch->endFilePos(),
            );

            $excludedTargetTypes = $branch->effectOnly()
                ? [
                    NodeType::CONDITION,
                    NodeType::EXCEPTION,
                    NodeType::RETURN_VALUE,
                    NodeType::HTTP_RESPONSE,
                    NodeType::CONTINUATION,
                    NodeType::CONTROL_BRANCH,
                    NodeType::LOOP_CONTROL,
                ]
                : [];

            $moved = $graph->reparentOutgoingBetween(
                $sourceId,
                $branch->startFilePos(),
                $branch->endFilePos(),
                $branchId,
                $excludedTargetTypes,
            );

            if ($moved === 0) {
                continue;
            }

            $branchType = $this->isLoopBranch($branch->label())
                ? NodeType::LOOP
                : NodeType::CONTROL_BRANCH;

            $graph->addNode(new Node(
                $branchId,
                $branchType,
                $branch->label(),
            ));

            $graph->addEdge(new Edge(
                $sourceId,
                $branchId,
                EdgeType::CALLS,
                strtolower(strtok($branch->label(), ' ')),
                $branch->startFilePos(),
            ));
        }
    }

    private function isLoopBranch(string $label): bool
    {
        return str_starts_with($label, 'FOREACH ')
            || str_starts_with($label, 'FOR ')
            || str_starts_with($label, 'WHILE ')
            || str_starts_with($label, 'DO WHILE ');
    }

    /**
     * @param array<string, true> $knownHandlers
     * @param array<string, string> $repositorySourceNodes
     * @param array<string, string> $serviceSourceNodes
     */
    private function applyGuardContinuations(
        Graph $graph,
        ProjectAnalysis $analysis,
        array $knownHandlers,
        array $repositorySourceNodes,
        array $serviceSourceNodes,
    ): void {
        $grouped = [];

        foreach ($analysis->guardClauses() as $guard) {
            $grouped[$guard->source()][] = $guard;
        }

        foreach ($grouped as $source => $guards) {
            usort(
                $guards,
                static fn ($left, $right): int =>
                    $left->continuesAfter() <=> $right->continuesAfter(),
            );

            [$currentSource] = $this->sourceNode(
                $source,
                $knownHandlers,
                $repositorySourceNodes,
                $serviceSourceNodes,
            );

            foreach ($guards as $guard) {
                $continuationId = 'continuation:'.hash(
                    'sha256',
                    $source.'|'.$guard->condition().'|'.$guard->continuesAfter(),
                );

                $graph->addNode(new Node(
                    $continuationId,
                    NodeType::CONTINUATION,
                    'CONTINUE',
                ));

                $graph->reparentOutgoingAfter(
                    $currentSource,
                    $guard->continuesAfter(),
                    $continuationId,
                );

                $graph->addEdge(new Edge(
                    $currentSource,
                    $continuationId,
                    EdgeType::CALLS,
                    'continue',
                    $guard->continuesAfter() + 1,
                ));

                $currentSource = $continuationId;
            }
        }
    }

    /**
     * @param array<string, true> $knownHandlers
     * @param array<string, string> $repositorySourceNodes
     * @param array<string, string> $serviceSourceNodes
     * @return array{string, NodeType}
     */
    private function sourceNode(
        string $source,
        array $knownHandlers,
        array $repositorySourceNodes,
        array $serviceSourceNodes,
    ): array {
        if (isset($knownHandlers[$source])) {
            return ['handler:'.$source, NodeType::HANDLER];
        }

        if (isset($repositorySourceNodes[$source])) {
            return [$repositorySourceNodes[$source], NodeType::REPOSITORY];
        }

        if (isset($serviceSourceNodes[$source])) {
            return [$serviceSourceNodes[$source], NodeType::SERVICE];
        }

        return ['controller:'.$source, NodeType::CONTROLLER];
    }

    /**
     * @param list<string> $methods
     */
    private function routeId(array $methods, ?string $path): string
    {
        return sprintf(
            'route:%s:%s',
            $methods === [] ? '*' : implode('|', $methods),
            $path ?? '<dynamic>',
        );
    }

    /**
     * @param list<string> $methods
     */
    private function routeLabel(array $methods, ?string $path): string
    {
        return sprintf(
            '%s %s',
            $methods === [] ? '*' : implode('|', $methods),
            $path ?? '<dynamic>',
        );
    }
}
