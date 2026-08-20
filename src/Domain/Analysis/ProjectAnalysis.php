<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Analysis;

final readonly class ProjectAnalysis
{
    /**
     * @param list<PhpAttribute> $attributes
     * @param list<SymfonyRoute> $routes
     * @param list<MessageDispatch> $messageDispatches
     * @param list<UnresolvedCall> $unresolvedCalls
     * @param list<MessageHandler> $messageHandlers
     * @param list<MessageRouting> $messageRoutings
     * @param list<ResolvedMessageRouting> $resolvedMessageRoutings
     * @param list<MessengerTransport> $messengerTransports
     * @param list<RepositoryCall> $repositoryCalls
     * @param list<HttpCall> $httpCalls
     * @param list<ServiceCall> $serviceCalls
     * @param list<DatabaseEffect> $databaseEffects
     * @param list<ApplicationEffect> $applicationEffects
     * @param list<ThrownException> $thrownExceptions
     * @param list<MethodReturn> $methodReturns
     * @param list<HttpResponse> $httpResponses
     * @param list<GuardClause> $guardClauses
     * @param list<ControlBranch> $controlBranches
     * @param list<LoopControl> $loopControls
     */
    public function __construct(
        private ProjectStatistics $statistics,
        private array $attributes,
        private array $routes,
        private array $messageDispatches = [],
        private array $unresolvedCalls = [],
        private array $messageHandlers = [],
        private array $messageRoutings = [],
        private array $resolvedMessageRoutings = [],
        private array $messengerTransports = [],
        private array $repositoryCalls = [],
        private array $httpCalls = [],
        private array $serviceCalls = [],
        private array $databaseEffects = [],
        private array $applicationEffects = [],
        private array $thrownExceptions = [],
        private array $methodReturns = [],
        private array $httpResponses = [],
        private array $guardClauses = [],
        private array $controlBranches = [],
        private array $loopControls = [],
    ) {
    }

    public function statistics(): ProjectStatistics { return $this->statistics; }

    /** @return list<PhpAttribute> */
    public function attributes(): array { return $this->attributes; }

    /** @return list<SymfonyRoute> */
    public function routes(): array { return $this->routes; }

    /** @return list<MessageDispatch> */
    public function messageDispatches(): array { return $this->messageDispatches; }

    /** @return list<UnresolvedCall> */
    public function unresolvedCalls(): array { return $this->unresolvedCalls; }

    /** @return list<MessageHandler> */
    public function messageHandlers(): array { return $this->messageHandlers; }

    /** @return list<MessageRouting> */
    public function messageRoutings(): array { return $this->messageRoutings; }

    /** @return list<ResolvedMessageRouting> */
    public function resolvedMessageRoutings(): array { return $this->resolvedMessageRoutings; }

    /** @return list<MessengerTransport> */
    public function messengerTransports(): array { return $this->messengerTransports; }

    /** @return list<RepositoryCall> */
    public function repositoryCalls(): array { return $this->repositoryCalls; }

    /** @return list<HttpCall> */
    public function httpCalls(): array { return $this->httpCalls; }

    /** @return list<ServiceCall> */
    public function serviceCalls(): array { return $this->serviceCalls; }

    /** @return list<DatabaseEffect> */
    public function databaseEffects(): array { return $this->databaseEffects; }

    /** @return list<ApplicationEffect> */
    public function applicationEffects(): array { return $this->applicationEffects; }

    /** @return list<ThrownException> */
    public function thrownExceptions(): array { return $this->thrownExceptions; }

    /** @return list<MethodReturn> */
    public function methodReturns(): array { return $this->methodReturns; }

    /** @return list<HttpResponse> */
    public function httpResponses(): array { return $this->httpResponses; }

    /** @return list<GuardClause> */
    public function guardClauses(): array { return $this->guardClauses; }

    /** @return list<ControlBranch> */
    public function controlBranches(): array { return $this->controlBranches; }

    /** @return list<LoopControl> */
    public function loopControls(): array { return $this->loopControls; }
}
