<?php

declare(strict_types=1);

namespace PhpFlow\Ast;

use PhpFlow\Domain\Analysis\MessageDispatch;
use PhpFlow\Domain\Analysis\HttpCall;
use PhpFlow\Domain\Analysis\DatabaseEffect;
use PhpFlow\Domain\Analysis\ApplicationEffect;
use PhpFlow\Domain\Analysis\ThrownException;
use PhpFlow\Domain\Analysis\MethodReturn;
use PhpFlow\Domain\Analysis\HttpResponse;
use PhpFlow\Domain\Analysis\GuardClause;
use PhpFlow\Domain\Analysis\ControlBranch;
use PhpFlow\Domain\Analysis\LoopControl;
use PhpFlow\Domain\Analysis\UnreachableRange;
use PhpFlow\Domain\Analysis\MessageHandler;
use PhpFlow\Domain\Analysis\RepositoryCall;
use PhpFlow\Domain\Analysis\ServiceCall;
use PhpFlow\Domain\Analysis\SourcePosition;
use PhpFlow\Domain\Analysis\PhpAttribute;
use PhpFlow\Domain\Analysis\ProjectAnalysis;
use PhpFlow\Domain\Analysis\ProjectStatistics;
use PhpFlow\Domain\Analysis\SymfonyRoute;
use PhpFlow\Domain\Analysis\UnresolvedCall;
use PhpFlow\Domain\Project;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

final class ProjectAstAnalyzer
{
    private readonly Parser $parser;
    private readonly Standard $printer;
    private readonly ProjectIndexer $indexer;

    public function __construct(?Parser $parser = null, ?ProjectIndexer $indexer = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->createForNewestSupportedVersion();
        $this->printer = new Standard();
        $this->indexer = $indexer ?? new ProjectIndexer();
    }

    public function analyze(Project $project): ProjectAnalysis
    {
        $projectIndex = $this->indexer->index($project);

        $collector = new class($this->printer, $projectIndex) extends NodeVisitorAbstract {
            public int $classes = 0;
            public int $interfaces = 0;
            public int $traits = 0;
            public int $enums = 0;

            /** @var list<PhpAttribute> */
            public array $attributes = [];

            /** @var list<SymfonyRoute> */
            public array $routes = [];

            /** @var list<MessageDispatch> */
            public array $messageDispatches = [];

            /** @var list<UnresolvedCall> */
            public array $unresolvedCalls = [];

            /** @var list<MessageHandler> */
            public array $messageHandlers = [];

            /** @var list<RepositoryCall> */
            public array $repositoryCalls = [];

            /** @var list<HttpCall> */
            public array $httpCalls = [];

            /** @var list<ServiceCall> */
            public array $serviceCalls = [];

            /** @var list<DatabaseEffect> */
            public array $databaseEffects = [];

            /** @var list<ApplicationEffect> */
            public array $applicationEffects = [];

            /** @var list<ThrownException> */
            public array $thrownExceptions = [];

            /** @var list<MethodReturn> */
            public array $methodReturns = [];

            /** @var list<HttpResponse> */
            public array $httpResponses = [];

            /** @var list<GuardClause> */
            public array $guardClauses = [];

            /** @var list<ControlBranch> */
            public array $controlBranches = [];

            /** @var list<LoopControl> */
            public array $loopControls = [];

            /** @var list<UnreachableRange> */
            public array $unreachableRanges = [];

            private ?string $className = null;
            private ?string $methodName = null;
            private ?MethodContext $methodContext = null;
            private bool $currentClassIsMessageHandler = false;

            /** @var array<string, string> */
            private array $injectedServices = [];

            /** @var array<string, string> */
            private array $injectedParameters = [];

            /** @var array<string, string> */
            private array $classStringConstants = [];

            /** @var array<string, string> */
            private array $classStringMethods = [];

            public function __construct(
                private readonly Standard $printer,
                private readonly ProjectIndex $projectIndex,
            ) {
            }

            public function enterNode(Node $node): null
            {
                match (true) {
                    $node instanceof Node\Stmt\Class_ && !$node->isAnonymous() => ++$this->classes,
                    $node instanceof Node\Stmt\Interface_ => ++$this->interfaces,
                    $node instanceof Node\Stmt\Trait_ => ++$this->traits,
                    $node instanceof Node\Stmt\Enum_ => ++$this->enums,
                    default => null,
                };

                if ($node instanceof Node\Stmt\ClassLike) {
                    $this->className = $node->namespacedName?->toString();
                    $this->currentClassIsMessageHandler = $this->hasAsMessageHandlerAttribute($node->attrGroups);
                    $this->injectedServices = [];
                    $this->injectedParameters = [];
                    $this->classStringConstants = [];
                    $this->classStringMethods = [];

                    if ($node instanceof Node\Stmt\Class_) {
                        $this->indexStaticStringHelpers($node);
                    }

                    $this->collectAttributes(
                        $node->attrGroups,
                        $this->className ?? $node->name?->toString() ?? '<anonymous>',
                    );
                }

                if ($node instanceof Node\Stmt\ClassConst) {
                    foreach ($node->consts as $const) {
                        if ($const->value instanceof Node\Scalar\String_) {
                            $this->classStringConstants[$const->name->toString()] = $const->value->value;
                        }
                    }
                }

                if ($node instanceof Node\Stmt\ClassMethod) {
                    $staticString = $this->staticStringReturnedBy($node);

                    if ($staticString !== null) {
                        $this->classStringMethods[$node->name->toString()] = $staticString;
                    }

                    $this->methodName = $node->name->toString();
                    $this->methodContext = new MethodContext();

                    if ($this->methodName === '__construct') {
                        $this->collectPromotedServices($node);
                    }
                    $this->collectAttributes(
                        $node->attrGroups,
                        $this->source(),
                        $this->className,
                        $this->methodName,
                    );

                    $methodIsMessageHandler = $this->hasAsMessageHandlerAttribute($node->attrGroups);
                    $methodName = $node->name->toString();

                    if (
                        ($this->currentClassIsMessageHandler && $methodName === '__invoke')
                        || $methodIsMessageHandler
                    ) {
                        $message = $this->messageTypeFromHandlerMethod($node);

                        if ($message !== null && $this->className !== null) {
                            $this->messageHandlers[] = new MessageHandler(
                                $message,
                                $this->className.'::'.$methodName,
                            );
                        }
                    }
                }

                return null;
            }

            public function leaveNode(Node $node): null
            {
                if ($node instanceof Node\Expr\AssignOp\Concat && $this->methodContext !== null) {
                    $this->rememberConcatAssignment($node);
                }

                if ($node instanceof Node\Expr\Assign && $this->methodContext !== null) {
                    $this->rememberAssignment($node);
                }

                if ($node instanceof Node\Expr\MethodCall && $this->isDispatchCall($node)) {
                    $message = $this->messageClassFromDispatch($node);

                    if ($message !== null) {
                        $this->messageDispatches[] = new MessageDispatch($this->source(), $message, $this->sourcePosition($node));
                    }
                }

                if ($node instanceof Node\Expr\Assign && $this->methodName === '__construct') {
                    $this->rememberInjectedServiceAssignment($node);
                }

                if ($node instanceof Node\Expr\MethodCall) {
                    $this->rememberQueryBuilderMutation($node);
                    $this->collectRepositoryCall($node);
                    $this->collectDatabaseEffect($node);
                    $this->collectApplicationEffect($node);
                    $this->collectHttpCall($node);
                    $this->collectServiceCall($node);
                }

                if ($node instanceof Node\Expr\MethodCall && !$this->isDispatchCall($node)) {
                    $message = $this->messageClassFromInheritedWrapper($node);

                    if ($message !== null) {
                        $this->messageDispatches[] = new MessageDispatch($this->source(), $message, $this->sourcePosition($node));
                    } elseif (
                        $node->var instanceof Node\Expr\Variable
                        && $node->var->name === 'this'
                        && $node->name instanceof Node\Identifier
                        && isset($node->args[0])
                    ) {
                        $argumentType = $this->messageClassFromExpression($node->args[0]->value);

                        if ($argumentType !== null) {
                            $this->unresolvedCalls[] = new UnresolvedCall(
                                $this->source(),
                                $node->name->toString(),
                                $argumentType,
                            );
                        }
                    }
                }

                if ($node instanceof Node\Expr\Throw_) {
                    $this->collectThrownException($node);
                }

                if ($node instanceof Node\Stmt\Return_ && $node->expr !== null) {
                    $this->collectHttpResponse($node);
                    $this->collectMethodReturn($node);
                }

                if ($node instanceof Node\Stmt\If_) {
                    $this->collectConditionalEffectBranches($node);
                    $this->collectGuardClause($node);
                }

                if ($node instanceof Node\Expr\Match_) {
                    $this->collectMatchEffectBranches($node);
                }

                if ($node instanceof Node\Expr\Ternary) {
                    $this->collectTernaryEffectBranches($node);
                }

                if ($node instanceof Node\Expr\BinaryOp\Coalesce) {
                    $this->collectCoalesceEffectBranch($node);
                }

                if (
                    $node instanceof Node\Expr\BinaryOp\BooleanAnd
                    || $node instanceof Node\Expr\BinaryOp\LogicalAnd
                    || $node instanceof Node\Expr\BinaryOp\BooleanOr
                    || $node instanceof Node\Expr\BinaryOp\LogicalOr
                ) {
                    $this->collectShortCircuitEffectBranch($node);
                }

                if (
                    $node instanceof Node\Stmt\Foreach_
                    || $node instanceof Node\Stmt\For_
                    || $node instanceof Node\Stmt\While_
                    || $node instanceof Node\Stmt\Do_
                ) {
                    $this->collectLoopBranch($node);
                }

                if ($node instanceof Node\Stmt\TryCatch) {
                    $this->collectTryCatchBranches($node);
                }

                if (
                    $node instanceof Node\Stmt\Break_
                    || $node instanceof Node\Stmt\Continue_
                ) {
                    $this->collectLoopControl($node);
                }

                if ($node instanceof Node\Stmt\ClassMethod) {
                    $this->collectUnreachableRanges($node->stmts ?? []);
                    $this->methodName = null;
                    $this->methodContext = null;
                }

                if ($node instanceof Node\Stmt\ClassLike) {
                    $this->className = null;
                    $this->currentClassIsMessageHandler = false;
                    $this->injectedServices = [];
                    $this->injectedParameters = [];
                    $this->classStringConstants = [];
                    $this->classStringMethods = [];
                }

                return null;
            }

            private function indexStaticStringHelpers(Node\Stmt\Class_ $class): void
            {
                foreach ($class->stmts as $statement) {
                    if ($statement instanceof Node\Stmt\ClassConst) {
                        foreach ($statement->consts as $const) {
                            $value = $this->staticStringExpression($const->value);

                            if ($value !== null) {
                                $this->classStringConstants[$const->name->toString()] = $value;
                            }
                        }

                        continue;
                    }

                    if ($statement instanceof Node\Stmt\ClassMethod) {
                        $value = $this->staticStringReturnedBy($statement);

                        if ($value !== null) {
                            $this->classStringMethods[$statement->name->toString()] = $value;
                        }
                    }
                }
            }

            private function staticStringReturnedBy(Node\Stmt\ClassMethod $method): ?string
            {
                foreach ($method->stmts ?? [] as $statement) {
                    if (
                        $statement instanceof Node\Stmt\Return_
                        && $statement->expr instanceof Node\Expr
                    ) {
                        return $this->staticStringExpression($statement->expr);
                    }
                }

                return null;
            }

            private function staticStringExpression(Node\Expr $expression): ?string
            {
                if ($expression instanceof Node\Scalar\String_) {
                    return $expression->value;
                }

                if ($expression instanceof Node\Scalar\InterpolatedString) {
                    $parts = [];

                    foreach ($expression->parts as $part) {
                        if (!$part instanceof Node\InterpolatedStringPart) {
                            return null;
                        }

                        $parts[] = $part->value;
                    }

                    return implode('', $parts);
                }

                if ($expression instanceof Node\Expr\BinaryOp\Concat) {
                    $left = $this->staticStringExpression($expression->left);
                    $right = $this->staticStringExpression($expression->right);

                    return $left !== null && $right !== null
                        ? $left.$right
                        : null;
                }

                return null;
            }

            private function collectHttpResponse(Node\Stmt\Return_ $return): void
            {
                $response = $this->httpResponseFromExpression($return->expr);

                if ($response === null) {
                    return;
                }

                [$type, $status] = $response;

                $this->httpResponses[] = new HttpResponse(
                    $this->source(),
                    $type,
                    $status,
                    $this->branchPath($return),
                    $this->sourcePosition($return),
                );
            }

            /** @return array{string, int|null}|null */
            private function httpResponseFromExpression(Node\Expr $expression): ?array
            {
                if (
                    $expression instanceof Node\Expr\New_
                    && $expression->class instanceof Node\Name
                ) {
                    $class = $expression->class->toString();

                    if (
                        $class === 'Symfony\\Component\\HttpFoundation\\JsonResponse'
                        || str_ends_with($class, '\\JsonResponse')
                    ) {
                        return [
                            'JsonResponse',
                            isset($expression->args[1])
                                ? $this->httpStatusCode($expression->args[1]->value)
                                : 200,
                        ];
                    }

                    if (
                        $class === 'Symfony\\Component\\HttpFoundation\\RedirectResponse'
                        || str_ends_with($class, '\\RedirectResponse')
                    ) {
                        return [
                            'RedirectResponse',
                            isset($expression->args[1])
                                ? $this->httpStatusCode($expression->args[1]->value)
                                : 302,
                        ];
                    }

                    if (
                        $class === 'Symfony\\Component\\HttpFoundation\\Response'
                        || str_ends_with($class, '\\Response')
                    ) {
                        return [
                            'Response',
                            isset($expression->args[1])
                                ? $this->httpStatusCode($expression->args[1]->value)
                                : 200,
                        ];
                    }
                }

                if (
                    $expression instanceof Node\Expr\MethodCall
                    && $expression->var instanceof Node\Expr\Variable
                    && $expression->var->name === 'this'
                    && $expression->name instanceof Node\Identifier
                ) {
                    $method = $expression->name->toString();

                    if ($method === 'json') {
                        return [
                            'JsonResponse',
                            isset($expression->args[1])
                                ? $this->httpStatusCode($expression->args[1]->value)
                                : 200,
                        ];
                    }

                    if (in_array($method, ['redirect', 'redirectToRoute'], true)) {
                        $statusIndex = $method === 'redirect' ? 1 : 2;

                        return [
                            'RedirectResponse',
                            isset($expression->args[$statusIndex])
                                ? $this->httpStatusCode($expression->args[$statusIndex]->value)
                                : 302,
                        ];
                    }
                }

                return null;
            }

            private function httpStatusCode(Node\Expr $expression): ?int
            {
                if ($expression instanceof Node\Scalar\Int_) {
                    return $expression->value;
                }

                if (
                    $expression instanceof Node\Expr\ClassConstFetch
                    && $expression->name instanceof Node\Identifier
                ) {
                    return match ($expression->name->toString()) {
                        'HTTP_CONTINUE' => 100,
                        'HTTP_OK' => 200,
                        'HTTP_CREATED' => 201,
                        'HTTP_ACCEPTED' => 202,
                        'HTTP_NO_CONTENT' => 204,
                        'HTTP_MOVED_PERMANENTLY' => 301,
                        'HTTP_FOUND' => 302,
                        'HTTP_SEE_OTHER' => 303,
                        'HTTP_TEMPORARY_REDIRECT' => 307,
                        'HTTP_PERMANENTLY_REDIRECT' => 308,
                        'HTTP_BAD_REQUEST' => 400,
                        'HTTP_UNAUTHORIZED' => 401,
                        'HTTP_FORBIDDEN' => 403,
                        'HTTP_NOT_FOUND' => 404,
                        'HTTP_CONFLICT' => 409,
                        'HTTP_UNPROCESSABLE_ENTITY' => 422,
                        'HTTP_TOO_MANY_REQUESTS' => 429,
                        'HTTP_INTERNAL_SERVER_ERROR' => 500,
                        'HTTP_BAD_GATEWAY' => 502,
                        'HTTP_SERVICE_UNAVAILABLE' => 503,
                        default => null,
                    };
                }

                return null;
            }

            private function collectMethodReturn(Node\Stmt\Return_ $return): void
            {
                if ($return->expr instanceof Node\Expr\Match_) {
                    $this->collectMatchReturns($return->expr, $return);

                    return;
                }

                if ($this->httpResponseFromExpression($return->expr) !== null) {
                    return;
                }

                $type = $this->returnedTypeFromExpression($return->expr);

                if ($type === null) {
                    return;
                }

                $this->methodReturns[] = new MethodReturn(
                    $this->source(),
                    $type,
                    $this->branchPath($return),
                    $this->sourcePosition($return),
                );
            }

            private function collectMatchReturns(
                Node\Expr\Match_ $match,
                Node\Stmt\Return_ $return,
            ): void {
                $outerBranch = $this->branchPath($return);

                foreach ($match->arms as $arm) {
                    $type = $this->returnedTypeFromExpression($arm->body);

                    if ($type === null) {
                        continue;
                    }

                    $armLabel = $arm->conds === null
                        ? 'MATCH default'
                        : 'MATCH '.implode(', ', array_map(
                            fn (Node\Expr $condition): string =>
                                $this->printer->prettyPrintExpr($condition),
                            $arm->conds,
                        ));

                    $branch = $outerBranch === null
                        ? $armLabel
                        : $outerBranch.' / '.$armLabel;

                    $this->methodReturns[] = new MethodReturn(
                        $this->source(),
                        $type,
                        $branch,
                        $this->sourcePosition($arm),
                    );
                }
            }

            private function returnedTypeFromExpression(Node\Expr $expression): ?string
            {
                if (
                    $expression instanceof Node\Expr\New_
                    && $expression->class instanceof Node\Name
                ) {
                    return $expression->class->toString();
                }

                if (
                    $expression instanceof Node\Expr\Variable
                    && is_string($expression->name)
                    && $this->methodContext !== null
                ) {
                    return $this->methodContext->resolveObject($expression->name);
                }

                return null;
            }

            /**
             * @param list<Node\Stmt> $statements
             */
            private function collectUnreachableRanges(array $statements): void
            {
                $terminated = false;
                $unreachableStart = null;
                $unreachableEnd = null;

                foreach ($statements as $statement) {
                    if ($terminated) {
                        $unreachableStart ??= $statement->getStartFilePos();
                        $unreachableEnd = $statement->getEndFilePos();

                        continue;
                    }

                    $this->collectNestedUnreachableRanges($statement);

                    if ($this->statementTerminatesCurrentBlock($statement)) {
                        $terminated = true;
                    }
                }

                if (
                    $unreachableStart !== null
                    && $unreachableEnd !== null
                    && $unreachableStart >= 0
                    && $unreachableEnd >= $unreachableStart
                ) {
                    $this->unreachableRanges[] = new UnreachableRange(
                        $this->source(),
                        $unreachableStart,
                        $unreachableEnd,
                    );
                }
            }

            private function collectNestedUnreachableRanges(Node\Stmt $statement): void
            {
                if ($statement instanceof Node\Stmt\If_) {
                    $this->collectUnreachableRanges($statement->stmts);

                    foreach ($statement->elseifs as $elseif) {
                        $this->collectUnreachableRanges($elseif->stmts);
                    }

                    if ($statement->else !== null) {
                        $this->collectUnreachableRanges($statement->else->stmts);
                    }

                    return;
                }

                if (
                    $statement instanceof Node\Stmt\Foreach_
                    || $statement instanceof Node\Stmt\For_
                    || $statement instanceof Node\Stmt\While_
                    || $statement instanceof Node\Stmt\Do_
                ) {
                    $this->collectUnreachableRanges($statement->stmts);

                    return;
                }

                if ($statement instanceof Node\Stmt\TryCatch) {
                    $this->collectUnreachableRanges($statement->stmts);

                    foreach ($statement->catches as $catch) {
                        $this->collectUnreachableRanges($catch->stmts);
                    }

                    if ($statement->finally !== null) {
                        $this->collectUnreachableRanges($statement->finally->stmts);
                    }
                }
            }

            private function statementTerminatesCurrentBlock(Node\Stmt $statement): bool
            {
                if (
                    $statement instanceof Node\Stmt\Return_
                    || $statement instanceof Node\Stmt\Break_
                    || $statement instanceof Node\Stmt\Continue_
                ) {
                    return true;
                }

                if ($statement instanceof Node\Stmt\Expression) {
                    if ($statement->expr instanceof Node\Expr\Throw_) {
                        return true;
                    }

                    if (
                        $statement->expr instanceof Node\Expr\Match_
                        && $this->matchTerminatesCurrentBlock($statement->expr)
                    ) {
                        return true;
                    }
                }

                if ($statement instanceof Node\Stmt\If_) {
                    return $this->ifTerminatesCurrentBlock($statement);
                }

                if ($statement instanceof Node\Stmt\TryCatch) {
                    return $this->tryCatchTerminatesCurrentBlock($statement);
                }

                return false;
            }

            private function matchTerminatesCurrentBlock(Node\Expr\Match_ $match): bool
            {
                $hasDefault = false;

                foreach ($match->arms as $arm) {
                    if ($arm->conds === null) {
                        $hasDefault = true;
                    }

                    if (!$this->expressionTerminatesCurrentBlock($arm->body)) {
                        return false;
                    }
                }

                return $hasDefault && $match->arms !== [];
            }

            private function expressionTerminatesCurrentBlock(Node\Expr $expression): bool
            {
                if ($expression instanceof Node\Expr\Throw_) {
                    return true;
                }

                if ($expression instanceof Node\Expr\Match_) {
                    return $this->matchTerminatesCurrentBlock($expression);
                }

                return false;
            }

            private function tryCatchTerminatesCurrentBlock(Node\Stmt\TryCatch $tryCatch): bool
            {
                if (
                    $tryCatch->finally !== null
                    && $this->statementsTerminateCurrentBlock($tryCatch->finally->stmts)
                ) {
                    return true;
                }

                if (!$this->statementsTerminateCurrentBlock($tryCatch->stmts)) {
                    return false;
                }

                if ($tryCatch->catches === []) {
                    return true;
                }

                foreach ($tryCatch->catches as $catch) {
                    if (!$this->statementsTerminateCurrentBlock($catch->stmts)) {
                        return false;
                    }
                }

                return true;
            }

            private function ifTerminatesCurrentBlock(Node\Stmt\If_ $if): bool
            {
                if ($if->else === null) {
                    return false;
                }

                if (!$this->statementsTerminateCurrentBlock($if->stmts)) {
                    return false;
                }

                foreach ($if->elseifs as $elseif) {
                    if (!$this->statementsTerminateCurrentBlock($elseif->stmts)) {
                        return false;
                    }
                }

                return $this->statementsTerminateCurrentBlock($if->else->stmts);
            }

            /**
             * @param list<Node\Stmt> $statements
             */
            private function statementsTerminateCurrentBlock(array $statements): bool
            {
                if ($statements === []) {
                    return false;
                }

                foreach ($statements as $statement) {
                    if ($this->statementTerminatesCurrentBlock($statement)) {
                        return true;
                    }
                }

                return false;
            }

            private function collectLoopControl(
                Node\Stmt\Break_|Node\Stmt\Continue_ $control,
            ): void {
                if (!$this->isInsideLoop($control)) {
                    return;
                }

                $level = 1;

                if ($control->num instanceof Node\Scalar\Int_) {
                    $level = max(1, $control->num->value);
                }

                $this->loopControls[] = new LoopControl(
                    $this->source(),
                    $control instanceof Node\Stmt\Break_ ? 'BREAK' : 'CONTINUE LOOP',
                    $level,
                    $this->branchPath($control),
                    $this->sourcePosition($control),
                );
            }

            private function isInsideLoop(Node $node): bool
            {
                $current = $node;

                while (($parent = $current->getAttribute('parent')) instanceof Node) {
                    if (
                        $parent instanceof Node\Stmt\Foreach_
                        || $parent instanceof Node\Stmt\For_
                        || $parent instanceof Node\Stmt\While_
                        || $parent instanceof Node\Stmt\Do_
                    ) {
                        return true;
                    }

                    $current = $parent;
                }

                return false;
            }

            private function collectLoopBranch(
                Node\Stmt\Foreach_|Node\Stmt\For_|Node\Stmt\While_|Node\Stmt\Do_ $loop,
            ): void {
                $statements = $loop->stmts;

                if ($statements === []) {
                    return;
                }

                $first = $statements[0];
                $last = $statements[array_key_last($statements)];
                $start = $first->getStartFilePos();
                $end = $last->getEndFilePos();

                if ($start < 0 || $end < 0) {
                    return;
                }

                $this->controlBranches[] = new ControlBranch(
                    $this->source(),
                    $this->loopLabel($loop),
                    $start,
                    $end,
                    $this->sourcePosition($loop),
                    false,
                );
            }

            private function loopLabel(
                Node\Stmt\Foreach_|Node\Stmt\For_|Node\Stmt\While_|Node\Stmt\Do_ $loop,
            ): string {
                if ($loop instanceof Node\Stmt\Foreach_) {
                    $iterable = $this->printer->prettyPrintExpr($loop->expr);
                    $value = $this->printer->prettyPrintExpr($loop->valueVar);

                    if ($loop->keyVar === null) {
                        return sprintf('FOREACH %s as %s', $iterable, $value);
                    }

                    return sprintf(
                        'FOREACH %s as %s => %s',
                        $iterable,
                        $this->printer->prettyPrintExpr($loop->keyVar),
                        $value,
                    );
                }

                if ($loop instanceof Node\Stmt\While_) {
                    return 'WHILE '.$this->printer->prettyPrintExpr($loop->cond);
                }

                if ($loop instanceof Node\Stmt\Do_) {
                    return 'DO WHILE '.$this->printer->prettyPrintExpr($loop->cond);
                }

                $init = implode(', ', array_map(
                    fn (Node\Expr $expr): string => $this->printer->prettyPrintExpr($expr),
                    $loop->init,
                ));
                $condition = implode(', ', array_map(
                    fn (Node\Expr $expr): string => $this->printer->prettyPrintExpr($expr),
                    $loop->cond,
                ));
                $next = implode(', ', array_map(
                    fn (Node\Expr $expr): string => $this->printer->prettyPrintExpr($expr),
                    $loop->loop,
                ));

                return sprintf(
                    'FOR %s; %s; %s',
                    $init,
                    $condition,
                    $next,
                );
            }

            private function collectConditionalEffectBranches(Node\Stmt\If_ $if): void
            {
                $this->collectEffectStatementBranch(
                    'IF '.$this->printer->prettyPrintExpr($if->cond),
                    $if->stmts,
                    $if,
                );

                foreach ($if->elseifs as $elseif) {
                    $this->collectEffectStatementBranch(
                        'ELSEIF '.$this->printer->prettyPrintExpr($elseif->cond),
                        $elseif->stmts,
                        $elseif,
                    );
                }

                if ($if->else !== null) {
                    $this->collectEffectStatementBranch(
                        'ELSE',
                        $if->else->stmts,
                        $if->else,
                    );
                }
            }

            private function collectTernaryEffectBranches(Node\Expr\Ternary $ternary): void
            {
                $condition = $this->printer->prettyPrintExpr($ternary->cond);

                if ($ternary->if !== null) {
                    $this->collectExpressionBranch(
                        'TERNARY '.$condition.' THEN',
                        $ternary->if,
                    );
                }

                $this->collectExpressionBranch(
                    'TERNARY '.$condition.' ELSE',
                    $ternary->else,
                );
            }

            private function collectCoalesceEffectBranch(Node\Expr\BinaryOp\Coalesce $coalesce): void
            {
                $this->collectExpressionBranch(
                    'COALESCE '.$this->printer->prettyPrintExpr($coalesce->left).' IS NULL',
                    $coalesce->right,
                );
            }

            private function collectShortCircuitEffectBranch(Node\Expr\BinaryOp $binary): void
            {
                $left = $this->printer->prettyPrintExpr($binary->left);

                $label = (
                    $binary instanceof Node\Expr\BinaryOp\BooleanAnd
                    || $binary instanceof Node\Expr\BinaryOp\LogicalAnd
                )
                    ? 'IF '.$left
                    : 'IF NOT ('.$left.')';

                $this->collectExpressionBranch(
                    $label,
                    $binary->right,
                );
            }

            private function collectExpressionBranch(
                string $label,
                Node\Expr $expression,
            ): void {
                $start = $expression->getStartFilePos();
                $end = $expression->getEndFilePos();

                if ($start < 0 || $end < 0) {
                    return;
                }

                $this->controlBranches[] = new ControlBranch(
                    $this->source(),
                    $label,
                    $start,
                    $end,
                    $this->sourcePosition($expression),
                    true,
                );
            }

            private function collectMatchEffectBranches(Node\Expr\Match_ $match): void
            {
                foreach ($match->arms as $arm) {
                    $start = $arm->body->getStartFilePos();
                    $end = $arm->body->getEndFilePos();

                    if ($start < 0 || $end < 0) {
                        continue;
                    }

                    $label = $arm->conds === null
                        ? 'MATCH default'
                        : 'MATCH '.implode(', ', array_map(
                            fn (Node\Expr $condition): string =>
                                $this->printer->prettyPrintExpr($condition),
                            $arm->conds,
                        ));

                    $this->controlBranches[] = new ControlBranch(
                        $this->source(),
                        $label,
                        $start,
                        $end,
                        $this->sourcePosition($arm),
                        true,
                    );
                }
            }

            /**
             * @param list<Node\Stmt> $statements
             */
            private function collectEffectStatementBranch(
                string $label,
                array $statements,
                Node $owner,
            ): void {
                $first = $statements[0] ?? null;
                $last = $statements[array_key_last($statements)] ?? null;

                if (!$first instanceof Node || !$last instanceof Node) {
                    return;
                }

                $start = $first->getStartFilePos();
                $end = $last->getEndFilePos();

                if ($start < 0 || $end < 0) {
                    return;
                }

                $this->controlBranches[] = new ControlBranch(
                    $this->source(),
                    $label,
                    $start,
                    $end,
                    $this->sourcePosition($owner),
                    true,
                );
            }

            private function collectTryCatchBranches(Node\Stmt\TryCatch $tryCatch): void
            {
                $this->collectStatementBranch(
                    'TRY',
                    $tryCatch->stmts,
                    $tryCatch,
                );

                foreach ($tryCatch->catches as $catch) {
                    $types = array_map(
                        static fn (Node\Name $type): string => $type->toString(),
                        $catch->types,
                    );

                    $this->collectStatementBranch(
                        'CATCH '.implode('|', $types),
                        $catch->stmts,
                        $catch,
                    );
                }

                if ($tryCatch->finally !== null) {
                    $this->collectStatementBranch(
                        'FINALLY',
                        $tryCatch->finally->stmts,
                        $tryCatch->finally,
                    );
                }
            }

            /**
             * @param list<Node\Stmt> $statements
             */
            private function collectStatementBranch(
                string $label,
                array $statements,
                Node $owner,
            ): void {
                $first = $statements[0] ?? null;
                $last = $statements[array_key_last($statements)] ?? null;

                if (!$first instanceof Node || !$last instanceof Node) {
                    return;
                }

                $start = $first->getStartFilePos();
                $end = $last->getEndFilePos();

                if ($start < 0 || $end < 0) {
                    return;
                }

                $this->controlBranches[] = new ControlBranch(
                    $this->source(),
                    $label,
                    $start,
                    $end,
                    $this->sourcePosition($owner),
                );
            }

            private function collectGuardClause(Node\Stmt\If_ $if): void
            {
                if (
                    $if->elseifs !== []
                    || $if->else !== null
                    || !$this->statementsTerminate($if->stmts)
                ) {
                    return;
                }

                $parent = $if->getAttribute('parent');

                if (!$parent instanceof Node\Stmt\ClassMethod) {
                    return;
                }

                $index = array_search($if, $parent->stmts ?? [], true);

                if (
                    $index === false
                    || $index === count($parent->stmts ?? []) - 1
                ) {
                    return;
                }

                $endFilePos = $if->getEndFilePos();

                if ($endFilePos < 0) {
                    return;
                }

                $this->guardClauses[] = new GuardClause(
                    $this->source(),
                    $this->printer->prettyPrintExpr($if->cond),
                    $endFilePos,
                    $this->sourcePosition($if),
                );
            }

            /**
             * @param list<Node\Stmt> $statements
             */
            private function statementsTerminate(array $statements): bool
            {
                $last = $statements[array_key_last($statements)] ?? null;

                if ($last instanceof Node\Stmt\Return_) {
                    return true;
                }

                return $last instanceof Node\Stmt\Expression
                    && $last->expr instanceof Node\Expr\Throw_;
            }

            private function branchPath(Node $node): ?string
            {
                $segments = [];
                $current = $node;

                while (($parent = $current->getAttribute('parent')) instanceof Node) {
                    if ($parent instanceof Node\Stmt\If_) {
                        if ($this->isDirectlyInsideStatements($current, $parent->stmts)) {
                            $segments[] = 'IF '.$this->printer->prettyPrintExpr($parent->cond);
                        }
                    } elseif ($parent instanceof Node\Stmt\ElseIf_) {
                        if ($this->isDirectlyInsideStatements($current, $parent->stmts)) {
                            $segments[] = 'ELSEIF '.$this->printer->prettyPrintExpr($parent->cond);
                        }
                    } elseif ($parent instanceof Node\Stmt\Else_) {
                        if ($this->isDirectlyInsideStatements($current, $parent->stmts)) {
                            $segments[] = 'ELSE';
                        }
                    } elseif ($parent instanceof Node\MatchArm) {
                        $arm = $parent->conds === null
                            ? 'default'
                            : implode(', ', array_map(
                                fn (Node\Expr $condition): string => $this->printer->prettyPrintExpr($condition),
                                $parent->conds,
                            ));

                        $segments[] = 'MATCH '.$arm;
                    }

                    $current = $parent;
                }

                if ($segments === []) {
                    return null;
                }

                return implode(' / ', array_reverse($segments));
            }

            /**
             * @param list<Node\Stmt> $statements
             */
            private function isDirectlyInsideStatements(Node $node, array $statements): bool
            {
                foreach ($statements as $statement) {
                    if ($statement === $node || $this->containsNode($statement, $node)) {
                        return true;
                    }
                }

                return false;
            }

            private function containsNode(Node $root, Node $needle): bool
            {
                foreach ($root->getSubNodeNames() as $name) {
                    $value = $root->$name;

                    if ($value === $needle) {
                        return true;
                    }

                    if ($value instanceof Node && $this->containsNode($value, $needle)) {
                        return true;
                    }

                    if (is_array($value)) {
                        foreach ($value as $item) {
                            if ($item === $needle) {
                                return true;
                            }

                            if ($item instanceof Node && $this->containsNode($item, $needle)) {
                                return true;
                            }
                        }
                    }
                }

                return false;
            }

            private function collectThrownException(Node\Expr\Throw_ $throw): void
            {
                if (
                    !$throw->expr instanceof Node\Expr\New_
                    || !$throw->expr->class instanceof Node\Name
                ) {
                    return;
                }

                $this->thrownExceptions[] = new ThrownException(
                    $this->source(),
                    $throw->expr->class->toString(),
                    $this->branchPath($throw),
                    $this->sourcePosition($throw),
                );
            }

            /**
             * @template T
             * @param list<T> $items
             * @return list<T>
             */
            public function reachableItems(array $items): array
            {
                return array_values(array_filter(
                    $items,
                    function ($item): bool {
                        if (!method_exists($item, 'source') || !method_exists($item, 'position')) {
                            return true;
                        }

                        $position = $item->position();

                        if ($position === null) {
                            return true;
                        }

                        foreach ($this->unreachableRanges as $range) {
                            if (
                                $range->source() === $item->source()
                                && $range->contains($position->filePosition())
                            ) {
                                return false;
                            }
                        }

                        return true;
                    },
                ));
            }

            private function sourcePosition(Node $node): SourcePosition
            {
                return new SourcePosition(
                    $node->getStartLine(),
                    $node->getStartFilePos(),
                );
            }

            private function source(): string
            {
                if ($this->className === null) {
                    return '<unknown>';
                }

                return $this->methodName === null
                    ? $this->className
                    : sprintf('%s::%s', $this->className, $this->methodName);
            }

            private function isDispatchCall(Node\Expr\MethodCall $call): bool
            {
                return $call->name instanceof Node\Identifier
                    && $call->name->toString() === 'dispatch'
                    && isset($call->args[0]);
            }

            private function messageClassFromDispatch(Node\Expr\MethodCall $call): ?string
            {
                return $this->messageClassFromExpression($call->args[0]->value);
            }

            private function messageClassFromInheritedWrapper(Node\Expr\MethodCall $call): ?string
            {
                if (
                    $this->className === null
                    || !$call->name instanceof Node\Identifier
                    || !$call->var instanceof Node\Expr\Variable
                    || $call->var->name !== 'this'
                ) {
                    return null;
                }

                $method = $this->projectIndex->resolveMethod(
                    $this->className,
                    $call->name->toString(),
                );

                if ($method === null) {
                    return null;
                }

                $position = $method->dispatchedParameterPosition();
                if ($position === null || !isset($call->args[$position])) {
                    return null;
                }

                return $this->messageClassFromExpression($call->args[$position]->value);
            }

            private function messageClassFromExpression(Node\Expr $value): ?string
            {
                if ($value instanceof Node\Expr\New_ && $value->class instanceof Node\Name) {
                    return $value->class->toString();
                }

                if (
                    $value instanceof Node\Expr\Variable
                    && is_string($value->name)
                    && $this->methodContext !== null
                ) {
                    return $this->methodContext->resolveObject($value->name);
                }

                return null;
            }

            private function rememberConcatAssignment(Node\Expr\AssignOp\Concat $assign): void
            {
                if (
                    !$assign->var instanceof Node\Expr\Variable
                    || !is_string($assign->var->name)
                    || $this->methodContext === null
                ) {
                    return;
                }

                $current = $this->methodContext->resolveString($assign->var->name);
                $suffix = $this->stringValue($assign->expr);

                if ($current !== null && $suffix !== null) {
                    $this->methodContext->rememberString(
                        $assign->var->name,
                        $current.$suffix,
                    );
                }
            }

            private function rememberAssignment(Node\Expr\Assign $assign): void
            {
                if (
                    !$assign->var instanceof Node\Expr\Variable
                    || !is_string($assign->var->name)
                    || $this->methodContext === null
                ) {
                    return;
                }

                $variable = $assign->var->name;

                if (
                    $assign->expr instanceof Node\Expr\MethodCall
                    && $assign->expr->name instanceof Node\Identifier
                    && $assign->expr->name->toString() === 'createQueryBuilder'
                    && $this->isDatabaseCallReceiver($assign->expr)
                ) {
                    $this->methodContext->rememberQueryBuilder($variable);

                    return;
                }

                $stringValue = $this->stringValue($assign->expr);

                if ($stringValue !== null) {
                    $this->methodContext->rememberString($variable, $stringValue);

                    return;
                }

                if ($assign->expr instanceof Node\Expr\New_ && $assign->expr->class instanceof Node\Name) {
                    $this->methodContext->rememberObject($variable, $assign->expr->class->toString());

                    return;
                }

                // Do not keep stale type information after an unrelated reassignment.
                $this->methodContext->forget($variable);
            }

            private function collectPromotedServices(Node\Stmt\ClassMethod $constructor): void
            {
                foreach ($constructor->params as $parameter) {
                    if ($parameter->flags === 0 || !is_string($parameter->var->name)) {
                        continue;
                    }

                    $property = $parameter->var->name;
                    $autowireService = $this->autowireArgument($parameter->attrGroups, 'service');
                    $autowireParam = $this->autowireArgument($parameter->attrGroups, 'param');

                    if ($autowireParam !== null) {
                        $this->injectedParameters[$property] = $autowireParam;
                    }

                    if ($autowireService !== null) {
                        $this->injectedServices[$property] = $autowireService;
                        continue;
                    }

                    if ($parameter->type instanceof Node\Name) {
                        $this->injectedServices[$property] = $parameter->type->toString();
                    }
                }
            }

            /** @param list<Node\AttributeGroup> $groups */
            private function autowireArgument(array $groups, string $argument): ?string
            {
                foreach ($groups as $group) {
                    foreach ($group->attrs as $attribute) {
                        $name = $attribute->name->getAttribute('resolvedName')?->toString()
                            ?? $attribute->name->toString();

                        if ($name !== 'Symfony\\Component\\DependencyInjection\\Attribute\\Autowire') {
                            continue;
                        }

                        foreach ($attribute->args as $arg) {
                            if ($arg->name?->toString() !== $argument) {
                                continue;
                            }

                            if ($arg->value instanceof Node\Scalar\String_) {
                                return $arg->value->value;
                            }

                            if (
                                $argument === 'service'
                                && $arg->value instanceof Node\Expr\ClassConstFetch
                                && $arg->value->name instanceof Node\Identifier
                                && $arg->value->name->toString() === 'class'
                                && $arg->value->class instanceof Node\Name
                            ) {
                                return $arg->value->class->toString();
                            }
                        }
                    }
                }

                return null;
            }

            private function rememberInjectedServiceAssignment(Node\Expr\Assign $assign): void
            {
                if (
                    !$assign->var instanceof Node\Expr\PropertyFetch
                    || !$assign->var->var instanceof Node\Expr\Variable
                    || $assign->var->var->name !== 'this'
                    || !$assign->var->name instanceof Node\Identifier
                    || !$assign->expr instanceof Node\Expr\Variable
                    || !is_string($assign->expr->name)
                ) {
                    return;
                }

                $sourceVariable = $assign->expr->name;

                if (isset($this->injectedServices[$sourceVariable])) {
                    $this->injectedServices[$assign->var->name->toString()] = $this->injectedServices[$sourceVariable];

                    return;
                }

                if ($this->methodContext !== null) {
                    $class = $this->methodContext->resolveObject($sourceVariable);

                    if ($class !== null) {
                        $this->injectedServices[$assign->var->name->toString()] = $class;
                    }
                }
            }

            private function collectRepositoryCall(Node\Expr\MethodCall $call): void
            {
                if (
                    !$call->var instanceof Node\Expr\PropertyFetch
                    || !$call->var->var instanceof Node\Expr\Variable
                    || $call->var->var->name !== 'this'
                    || !$call->var->name instanceof Node\Identifier
                    || !$call->name instanceof Node\Identifier
                ) {
                    return;
                }

                $property = $call->var->name->toString();
                $service = $this->injectedServices[$property] ?? null;

                if ($service === null || !$this->looksLikeRepository($service)) {
                    return;
                }

                $this->repositoryCalls[] = new RepositoryCall(
                    $this->source(),
                    $service,
                    $call->name->toString(),
                    $this->sourcePosition($call),
                );
            }

            private function collectServiceCall(Node\Expr\MethodCall $call): void
            {
                if (
                    !$call->var instanceof Node\Expr\PropertyFetch
                    || !$call->var->var instanceof Node\Expr\Variable
                    || $call->var->var->name !== 'this'
                    || !$call->var->name instanceof Node\Identifier
                    || !$call->name instanceof Node\Identifier
                ) {
                    return;
                }

                $property = $call->var->name->toString();
                $service = $this->injectedServices[$property] ?? null;

                if (
                    $service === null
                    || $this->looksLikeRepository($service)
                    || ($call->name->toString() === 'request' && $this->looksLikeHttpClient($service))
                    || $this->isMessengerInfrastructureCall($service, $call->name->toString())
                    || $this->isResolvedDatabaseEffectCall($call, $service)
                    || $this->isApplicationEffectCall($call, $service)
                ) {
                    return;
                }

                $implementation = $this->projectIndex->uniqueImplementationOf($service);

                if (
                    $implementation === null
                    && $this->projectIndex->hasSymbol($service)
                    && !$this->projectIndex->isInterface($service)
                ) {
                    $implementation = $service;
                }

                $this->serviceCalls[] = new ServiceCall(
                    $this->source(),
                    $service,
                    $call->name->toString(),
                    $implementation,
                    $this->sourcePosition($call),
                );
            }

            private function isResolvedDatabaseEffectCall(
                Node\Expr\MethodCall $call,
                string $service,
            ): bool {
                if (!$call->name instanceof Node\Identifier) {
                    return false;
                }

                $method = $call->name->toString();

                if (!$this->isDatabaseInfrastructureCall($service, $method)) {
                    return false;
                }

                if (in_array($method, ['insert', 'update', 'delete'], true)) {
                    return isset($call->args[0])
                        && $this->stringValue($call->args[0]->value) !== null;
                }

                if (in_array($method, [
                    'executeStatement',
                    'executeQuery',
                    'fetchAssociative',
                    'fetchAllAssociative',
                    'fetchOne',
                    'prepare',
                ], true)
                    && $this->isDatabaseCallReceiver($call)
                ) {
                    return isset($call->args[0])
                        && $this->databaseStringValue($call->args[0]->value) !== null;
                }

                return in_array($method, [
                    'persist',
                    'remove',
                    'find',
                    'findOneBy',
                    'findBy',
                ], true);
            }

            private function isDatabaseInfrastructureCall(string $service, string $method): bool
            {
                if (!in_array($method, [
                    'insert',
                    'update',
                    'delete',
                    'executeStatement',
                    'executeQuery',
                    'fetchAssociative',
                    'fetchAllAssociative',
                    'fetchOne',
                    'prepare',
                    'persist',
                    'remove',
                    'find',
                    'findOneBy',
                    'findBy',
                ], true)) {
                    return false;
                }

                return $service === 'Doctrine\\DBAL\\Connection'
                    || str_starts_with($service, 'Doctrine\\DBAL\\')
                    || str_starts_with($service, 'Doctrine\\ORM\\');
            }

            private function isMessengerInfrastructureCall(string $service, string $method): bool
            {
                if ($method !== 'dispatch') {
                    return false;
                }

                return $service === 'Symfony\\Component\\Messenger\\MessageBusInterface'
                    || str_ends_with($service, '\\MessageBusInterface')
                    || str_ends_with($service, '\\MessageBus');
            }

            private function rememberQueryBuilderMutation(Node\Expr\MethodCall $call): void
            {
                if (
                    !$call->name instanceof Node\Identifier
                    || $this->methodContext === null
                ) {
                    return;
                }

                $variable = $this->queryBuilderVariable($call->var);

                if (
                    $variable === null
                    || $this->methodContext->queryBuilder($variable) === null
                ) {
                    return;
                }

                $method = $call->name->toString();

                if (in_array($method, ['update', 'delete', 'insert'], true)) {
                    $target = isset($call->args[0])
                        ? $this->stringValue($call->args[0]->value)
                        : null;

                    $this->methodContext->updateQueryBuilder(
                        $variable,
                        strtoupper($method),
                        $target,
                    );

                    return;
                }

                if ($method === 'select') {
                    $this->methodContext->updateQueryBuilder(
                        $variable,
                        'SELECT',
                    );

                    return;
                }

                if ($method === 'from') {
                    $target = isset($call->args[0])
                        ? $this->stringValue($call->args[0]->value)
                        : null;

                    $this->methodContext->updateQueryBuilder(
                        $variable,
                        null,
                        $target,
                    );
                }
            }

            private function queryBuilderVariable(Node\Expr $expression): ?string
            {
                if (
                    $expression instanceof Node\Expr\Variable
                    && is_string($expression->name)
                ) {
                    return $expression->name;
                }

                if ($expression instanceof Node\Expr\MethodCall) {
                    return $this->queryBuilderVariable($expression->var);
                }

                return null;
            }

            /** @return array{string, string}|null */
            private function queryBuilderExecution(Node\Expr\MethodCall $call): ?array
            {
                if (
                    !$call->name instanceof Node\Identifier
                    || $this->methodContext === null
                    || !in_array($call->name->toString(), [
                        'executeStatement',
                        'executeQuery',
                        'execute',
                    ], true)
                ) {
                    return null;
                }

                $variable = $this->queryBuilderVariable($call->var);

                if ($variable === null) {
                    return null;
                }

                $state = $this->methodContext->queryBuilder($variable);

                if (
                    $state === null
                    || $state['operation'] === null
                    || $state['target'] === null
                ) {
                    return null;
                }

                return [$state['operation'], $state['target']];
            }

            private function collectDatabaseEffect(Node\Expr\MethodCall $call): void
            {
                if (
                    !$call->name instanceof Node\Identifier
                    || $this->className === null
                    || $this->methodName === null
                ) {
                    return;
                }

                $method = $call->name->toString();
                $operation = null;
                $target = null;
                $sql = null;

                $queryBuilderEffect = $this->queryBuilderExecution($call);

                if ($queryBuilderEffect !== null) {
                    [$operation, $target] = $queryBuilderEffect;
                } elseif (
                    in_array($method, ['insert', 'update', 'delete'], true)
                    && $this->isDatabaseCallReceiver($call)
                ) {
                    $operation = strtoupper($method);
                    $target = isset($call->args[0])
                        ? $this->stringValue($call->args[0]->value)
                        : null;
                } elseif (
                    in_array($method, [
                    'executeStatement',
                    'executeQuery',
                    'fetchAssociative',
                    'fetchAllAssociative',
                    'fetchOne',
                    'prepare',
                ], true)) {
                    $sql = isset($call->args[0])
                        ? $this->databaseStringValue($call->args[0]->value)
                        : null;

                    if ($sql === null) {
                        return;
                    }

                    [$operation, $target] = $this->sqlOperationAndTarget($sql);
                } elseif (
                    in_array($method, ['persist', 'remove'], true)
                    && $this->isDatabaseCallReceiver($call)
                ) {
                    $operation = strtoupper($method);
                    $target = isset($call->args[0])
                        ? $this->expressionType($call->args[0]->value)
                        : null;
                } elseif (
                    in_array($method, ['find', 'findOneBy', 'findBy'], true)
                    && $this->isDatabaseCallReceiver($call)
                ) {
                    $operation = 'SELECT';
                    $target = isset($call->args[0])
                        ? $this->classNameValue($call->args[0]->value)
                        : null;
                }

                if ($operation === null) {
                    return;
                }

                $this->databaseEffects[] = new DatabaseEffect(
                    $this->source(),
                    $operation,
                    $target,
                    $sql,
                    $this->sourcePosition($call),
                );
            }

            private function isDatabaseCallReceiver(Node\Expr\MethodCall $call): bool
            {
                if (
                    $call->var instanceof Node\Expr\PropertyFetch
                    && $call->var->var instanceof Node\Expr\Variable
                    && $call->var->var->name === 'this'
                    && $call->var->name instanceof Node\Identifier
                ) {
                    $service = $this->injectedServices[$call->var->name->toString()] ?? null;

                    if ($service === null) {
                        return false;
                    }

                    return $service === 'Doctrine\\DBAL\\Connection'
                        || str_starts_with($service, 'Doctrine\\DBAL\\')
                        || str_starts_with($service, 'Doctrine\\ORM\\')
                        || str_ends_with($service, 'EntityManagerInterface')
                        || str_ends_with($service, 'ObjectManager');
                }

                return false;
            }

            private function databaseStringValue(Node\Expr $expression): ?string
            {
                $value = $this->stringValue($expression);

                if ($value !== null) {
                    return $value;
                }

                if (
                    $expression instanceof Node\Expr\Variable
                    && is_string($expression->name)
                    && $this->methodContext !== null
                ) {
                    return $this->methodContext->resolveString($expression->name);
                }

                if (
                    $expression instanceof Node\Expr\ClassConstFetch
                    && $expression->name instanceof Node\Identifier
                    && (
                        $expression->class instanceof Node\Name
                        && in_array(strtolower($expression->class->toString()), ['self', 'static'], true)
                    )
                ) {
                    return $this->classStringConstants[$expression->name->toString()] ?? null;
                }

                return null;
            }

            /** @return array{string, ?string} */
            private function sqlOperationAndTarget(string $sql): array
            {
                $trimmed = ltrim($sql);

                if (preg_match('/^INSERT\s+INTO\s+([`"\[\]A-Za-z0-9_.]+)/i', $trimmed, $matches)) {
                    return ['INSERT', trim($matches[1], '`"[]')];
                }

                if (preg_match('/^UPDATE\s+([`"\[\]A-Za-z0-9_.]+)/i', $trimmed, $matches)) {
                    return ['UPDATE', trim($matches[1], '`"[]')];
                }

                if (preg_match('/^DELETE\s+FROM\s+([`"\[\]A-Za-z0-9_.]+)/i', $trimmed, $matches)) {
                    return ['DELETE', trim($matches[1], '`"[]')];
                }

                if (preg_match('/^SELECT\b.*?\bFROM\s+([`"\[\]A-Za-z0-9_.]+)/is', $trimmed, $matches)) {
                    return ['SELECT', trim($matches[1], '`"[]')];
                }

                return ['SQL', null];
            }

            private function classNameValue(Node\Expr $expression): ?string
            {
                if (
                    $expression instanceof Node\Expr\ClassConstFetch
                    && $expression->name instanceof Node\Identifier
                    && strtolower($expression->name->toString()) === 'class'
                    && $expression->class instanceof Node\Name
                ) {
                    return $expression->class->getAttribute('resolvedName')?->toString()
                        ?? $expression->class->toString();
                }

                return null;
            }

            private function expressionType(Node\Expr $expression): ?string
            {
                if ($expression instanceof Node\Expr\New_ && $expression->class instanceof Node\Name) {
                    return $expression->class->getAttribute('resolvedName')?->toString()
                        ?? $expression->class->toString();
                }

                if ($expression instanceof Node\Expr\Variable && is_string($expression->name)) {
                    return '$'.$expression->name;
                }

                return null;
            }

            private function collectApplicationEffect(Node\Expr\MethodCall $call): void
            {
                if (
                    !$call->var instanceof Node\Expr\PropertyFetch
                    || !$call->var->var instanceof Node\Expr\Variable
                    || $call->var->var->name !== 'this'
                    || !$call->var->name instanceof Node\Identifier
                    || !$call->name instanceof Node\Identifier
                ) {
                    return;
                }

                $service = $this->injectedServices[$call->var->name->toString()] ?? null;

                if ($service === null) {
                    return;
                }

                $effect = $this->applicationEffectDescriptor($call, $service);

                if ($effect === null) {
                    return;
                }

                [$kind, $operation, $target] = $effect;

                $this->applicationEffects[] = new ApplicationEffect(
                    $this->source(),
                    $kind,
                    $operation,
                    $target,
                    $this->sourcePosition($call),
                );
            }

            private function isApplicationEffectCall(
                Node\Expr\MethodCall $call,
                string $service,
            ): bool {
                return $this->applicationEffectDescriptor($call, $service) !== null;
            }

            /** @return array{string, string, ?string}|null */
            private function applicationEffectDescriptor(
                Node\Expr\MethodCall $call,
                string $service,
            ): ?array {
                if (!$call->name instanceof Node\Identifier) {
                    return null;
                }

                $method = $call->name->toString();

                if ($this->looksLikeMailer($service) && $method === 'send') {
                    return ['mail', 'SEND EMAIL', null];
                }

                if ($this->looksLikeFilesystem($service)) {
                    $operation = match ($method) {
                        'dumpFile' => 'WRITE',
                        'appendToFile' => 'APPEND',
                        'remove' => 'DELETE',
                        'rename' => 'MOVE',
                        'copy' => 'COPY',
                        'mkdir' => 'MKDIR',
                        'touch' => 'TOUCH',
                        default => null,
                    };

                    if ($operation !== null) {
                        $target = isset($call->args[0])
                            ? $this->stringValue($call->args[0]->value)
                            : null;

                        return ['filesystem', $operation, $target];
                    }
                }

                if ($this->looksLikeCache($service)) {
                    $operation = match ($method) {
                        'delete', 'deleteItem' => 'CACHE DELETE',
                        'clear' => 'CACHE CLEAR',
                        'save', 'saveDeferred' => 'CACHE SAVE',
                        'get' => 'CACHE GET',
                        default => null,
                    };

                    if ($operation !== null) {
                        $target = null;

                        if (
                            in_array($method, ['delete', 'deleteItem', 'get'], true)
                            && isset($call->args[0])
                        ) {
                            $target = $this->stringValue($call->args[0]->value);
                        }

                        return ['cache', $operation, $target];
                    }
                }

                return null;
            }

            private function looksLikeMailer(string $service): bool
            {
                return $service === 'Symfony\\Component\\Mailer\\MailerInterface'
                    || str_ends_with($service, '\\MailerInterface')
                    || str_ends_with($service, '\\Mailer');
            }

            private function looksLikeFilesystem(string $service): bool
            {
                return $service === 'Symfony\\Component\\Filesystem\\Filesystem'
                    || str_ends_with($service, '\\Filesystem');
            }

            private function looksLikeCache(string $service): bool
            {
                return $service === 'Symfony\\Contracts\\Cache\\CacheInterface'
                    || $service === 'Psr\\Cache\\CacheItemPoolInterface'
                    || str_ends_with($service, '\\CacheInterface')
                    || str_ends_with($service, '\\CacheItemPoolInterface')
                    || str_contains($service, '\\Cache\\');
            }

            private function collectHttpCall(Node\Expr\MethodCall $call): void
            {
                if (
                    !$call->var instanceof Node\Expr\PropertyFetch
                    || !$call->var->var instanceof Node\Expr\Variable
                    || $call->var->var->name !== 'this'
                    || !$call->var->name instanceof Node\Identifier
                    || !$call->name instanceof Node\Identifier
                    || $call->name->toString() !== 'request'
                ) {
                    return;
                }

                $property = $call->var->name->toString();
                $service = $this->injectedServices[$property] ?? null;

                if ($service === null || !$this->looksLikeHttpClient($service)) {
                    return;
                }

                $method = isset($call->args[0])
                    ? $this->stringValue($call->args[0]->value)
                    : null;

                $url = isset($call->args[1])
                    ? $this->stringValue($call->args[1]->value)
                    : null;

                $this->httpCalls[] = new HttpCall(
                    $this->source(),
                    $service,
                    $method,
                    $url,
                    $this->sourcePosition($call),
                );
            }

            private function looksLikeHttpClient(string $class): bool
            {
                return $class === 'Symfony\\Contracts\\HttpClient\\HttpClientInterface'
                    || str_ends_with($class, 'HttpClientInterface')
                    || str_ends_with($class, 'HttpClient')
                    || str_contains($class, '\\HttpClient\\');
            }

            private function stringValue(Node\Expr $expression): ?string
            {
                $staticValue = $this->staticStringExpression($expression);

                if ($staticValue !== null) {
                    return $staticValue;
                }

                if ($expression instanceof Node\Expr\BinaryOp\Concat) {
                    $left = $this->stringValue($expression->left);
                    $right = $this->stringValue($expression->right);

                    return $left !== null && $right !== null ? $left.$right : null;
                }

                if (
                    $expression instanceof Node\Expr\PropertyFetch
                    && $expression->var instanceof Node\Expr\Variable
                    && $expression->var->name === 'this'
                    && $expression->name instanceof Node\Identifier
                ) {
                    $parameter = $this->injectedParameters[$expression->name->toString()] ?? null;

                    return $parameter === null ? null : '%'.$parameter.'%';
                }

                if (
                    $expression instanceof Node\Expr\MethodCall
                    && $expression->var instanceof Node\Expr\Variable
                    && $expression->var->name === 'this'
                    && $expression->name instanceof Node\Identifier
                    && $expression->args === []
                ) {
                    return $this->classStringMethods[$expression->name->toString()] ?? null;
                }

                return null;
            }

            private function looksLikeRepository(string $class): bool
            {
                return str_ends_with($class, 'Repository')
                    || str_contains($class, '\\Repository\\')
                    || str_ends_with($class, 'RepositoryInterface')
                    || str_contains($class, '\\RepositoryInterface');
            }

            /**
             * @param list<Node\AttributeGroup> $groups
             */
            private function hasAsMessageHandlerAttribute(array $groups): bool
            {
                foreach ($groups as $group) {
                    foreach ($group->attrs as $attribute) {
                        $name = $attribute->name->getAttribute('resolvedName')?->toString()
                            ?? $attribute->name->toString();

                        if ($name === 'Symfony\\Component\\Messenger\\Attribute\\AsMessageHandler') {
                            return true;
                        }
                    }
                }

                return false;
            }

            private function messageTypeFromHandlerMethod(Node\Stmt\ClassMethod $method): ?string
            {
                $parameter = $method->params[0] ?? null;

                if ($parameter === null || !$parameter->type instanceof Node\Name) {
                    return null;
                }

                return $parameter->type->toString();
            }

            /**
             * @param list<Node\AttributeGroup> $groups
             */
            private function collectAttributes(
                array $groups,
                string $target,
                ?string $className = null,
                ?string $methodName = null,
            ): void {
                foreach ($groups as $group) {
                    foreach ($group->attrs as $attribute) {
                        $name = $attribute->name->getAttribute('resolvedName')?->toString()
                            ?? $attribute->name->toString();

                        $arguments = array_map(
                            fn (Node\Arg $arg): string => $this->printer->prettyPrintExpr($arg->value),
                            $attribute->args,
                        );

                        $this->attributes[] = new PhpAttribute($name, $target, $arguments);

                        if ($methodName !== null && $className !== null && $this->isSymfonyRoute($name)) {
                            $this->routes[] = $this->createRoute($attribute, $className, $methodName);
                        }
                    }
                }
            }

            private function isSymfonyRoute(string $name): bool
            {
                return $name === 'Symfony\\Component\\Routing\\Attribute\\Route'
                    || $name === 'Symfony\\Component\\Routing\\Annotation\\Route';
            }

            private function createRoute(Node\Attribute $attribute, string $className, string $methodName): SymfonyRoute
            {
                $path = null;
                $name = null;
                $methods = [];

                foreach ($attribute->args as $index => $argument) {
                    $argumentName = $argument->name?->toString();

                    if (($argumentName === null && $index === 0) || $argumentName === 'path') {
                        if ($argument->value instanceof String_) {
                            $path = $argument->value->value;
                        }
                    }

                    if ($argumentName === 'name' && $argument->value instanceof String_) {
                        $name = $argument->value->value;
                    }

                    if ($argumentName === 'methods' && $argument->value instanceof Array_) {
                        foreach ($argument->value->items as $item) {
                            if ($item?->value instanceof String_) {
                                $methods[] = $item->value->value;
                            }
                        }
                    }
                }

                return new SymfonyRoute(
                    controller: sprintf('%s::%s', $className, $methodName),
                    path: $path,
                    methods: $methods,
                    name: $name,
                );
            }
        };

        foreach ($project->sourceFiles() as $sourceFile) {
            $ast = $this->parser->parse(file_get_contents($sourceFile->path()));
            if ($ast === null) {
                continue;
            }

            $traverser = new NodeTraverser(
                new NameResolver(),
                new ParentConnectingVisitor(),
                $collector,
            );
            $traverser->traverse($ast);
        }

        return new ProjectAnalysis(
            new ProjectStatistics(
                $collector->classes,
                $collector->interfaces,
                $collector->traits,
                $collector->enums,
            ),
            $collector->attributes,
            $collector->routes,
            $collector->reachableItems($collector->messageDispatches),
            $collector->unresolvedCalls,
            $collector->messageHandlers,
            [],
            [],
            [],
            $collector->reachableItems($collector->repositoryCalls),
            $collector->reachableItems($collector->httpCalls),
            $collector->reachableItems($collector->serviceCalls),
            $collector->reachableItems($collector->databaseEffects),
            $collector->reachableItems($collector->applicationEffects),
            $collector->reachableItems($collector->thrownExceptions),
            $collector->reachableItems($collector->methodReturns),
            $collector->reachableItems($collector->httpResponses),
            $collector->guardClauses,
            $collector->controlBranches,
            $collector->reachableItems($collector->loopControls),
        );
    }
}
