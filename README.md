# PHPFlow

PHPFlow is a static analysis tool that reconstructs application flows from PHP source code.

It aims to help developers answer questions such as:

- Which routes trigger which commands?
- Which events are dispatched?
- Which handlers are executed?
- Which external services are called?

PHPFlow never executes the application it analyses.

## Requirements

- Docker with Docker Compose
- GNU Make (optional, but recommended)

## Getting started

```bash
make build
make composer
make scan
```

The `scan` command currently discovers PHP source files recursively while ignoring `vendor/`:

```bash
docker compose run --rm php php bin/phpflow scan /path/to/project
```

Run the test suite with:

```bash
make test
```

## Current milestone

The project scanner models a PHP project as a path plus a collection of source files. AST parsing and framework-specific analysis intentionally come later.

## Current scan output

PHPFlow now parses PHP source files statically and reports top-level declarations:
classes, interfaces, traits and enums.

```bash
make scan
make scan PROJECT_PATH=/app
```

## Commit 4

PHPFlow now collects PHP 8 attributes and detects Symfony `#[Route]` attributes on controller methods, including static paths, route names and HTTP methods.

## Commit 5

PHPFlow now detects simple Symfony Messenger dispatches such as:

```php
$this->bus->dispatch(new CreateUser(...));
```

The scanner reports the source method and the fully-qualified message class. Dynamic dispatches and variables are deliberately left for later iterations.

## Commit 6

PHPFlow performs its first small local data-flow analysis. A message can now be instantiated into a local variable and dispatched later in the same method:

```php
$command = new PreRegisterCompanies(...);
$this->commandBus->dispatch($command);
```

The local variable context is scoped to a method and invalidated when a variable is reassigned to an expression PHPFlow cannot resolve.

## Commit 7

PHPFlow now builds a small project index containing classes, parent classes and methods. It can resolve inherited wrapper methods that directly dispatch one of their parameters.

For example:

```php
abstract class AbstractController
{
    protected function handle(object $query): mixed
    {
        return $this->queryBus->dispatch($query);
    }
}

final class CompanyController extends AbstractController
{
    public function list(): mixed
    {
        $query = new ListCompanies();

        return $this->handle($query);
    }
}
```

PHPFlow resolves `CompanyController::list -> ListCompanies`.

This is intentionally limited to `$this->method(...)` wrappers whose implementation directly dispatches one of their parameters. It is the first step toward inter-method analysis, not a general-purpose PHP call graph.

## Commit 8

PHPFlow can now use an installed `vendor/` directory as a targeted symbol-resolution source. Vendor files are not part of the application scan or declaration statistics.

Only external parent classes referenced by application classes are located through Composer's generated PSR-4 map and indexed, recursively following their parents when necessary.

When a `$this->method($knownObject)` call cannot be resolved to a dispatch wrapper, PHPFlow keeps it as an unresolved call instead of silently discarding it.

## Commit 9

PHPFlow now indexes trait usage and can resolve methods supplied by traits, including traits located in `vendor/` through Composer's PSR-4 map.

This covers Symfony Messenger's `HandleTrait` pattern: an application method calling `$this->handle($query)` can be connected to the concrete query/command when the trait source is available in `vendor/`. If the trait cannot be loaded, the call remains unresolved.

## Commit 10

PHPFlow now builds an application flow graph from the analysis results.

Initial node types:
- route
- controller method
- message

Initial edge types:
- route `invokes` controller
- controller `dispatches` message

The graph is deliberately independent from Symfony and from any rendering format. Mermaid/JSON exporters will consume this model later.

## Commit 11

PHPFlow now detects Symfony Messenger handlers declared with `#[AsMessageHandler]` and an `__invoke(Message $message)` method.

The graph gains:
- handler nodes
- `message -> handled_by -> handler` edges

Because handlers are analyzed like any other class, a handler that dispatches another message already contributes another `handler -> dispatches -> message` relation. This is the first concrete step toward recursive application-flow traversal.

## Commit 12

PHPFlow can now recursively traverse the graph from a route and render the known flow as a tree.

Example:

```bash
php bin/phpflow inspect /workspace /users POST
```

The traversal follows every outgoing graph edge, supports branching, and stops safely when a node is revisited in the current path. Cycles are preserved and marked instead of being silently removed.

## Commit 13

PHPFlow can now export the complete application flow graph as Mermaid.

```bash
php bin/phpflow export:mermaid /workspace
```

Write it to a file:

```bash
php bin/phpflow export:mermaid /workspace --output=/tmp/phpflow.mmd
```

Or with Docker/Make:

```bash
make export-mermaid PROJECT_PATH=/path/to/project MERMAID_OUTPUT=/tmp/phpflow.mmd
```

The exporter consumes only the generic `Graph` model. It has no Symfony-specific logic.

## Commit 14

Mermaid export can now target a single route and only include the graph reachable from that route.

```bash
php bin/phpflow export:mermaid /workspace     --route=/companies     --method=GET     --max-depth=10     --output=/tmp/companies.mmd
```

Without `--route`, PHPFlow still exports the complete graph.

## Commit 15

PHPFlow now detects Symfony Messenger handlers declared either:

- with `#[AsMessageHandler]` on the class and `__invoke(Message $message)`;
- with `#[AsMessageHandler]` directly on a custom handler method.

Example:

```php
final class DeleteUserHandler
{
    #[AsMessageHandler]
    public function handle(DeleteUser $message): void
    {
    }
}
```

The Mermaid export command now reports a container-neutral success message, while the Makefile prints the actual host-side output path.

## Commit 16

PHPFlow reads Symfony Messenger routing from `config/packages/messenger.yaml` or `.yml`.
Dispatch edges are enriched as `sync` when no routing is configured, or `async: <transport>` when the message is routed to one or more senders.

This is static configuration analysis only: PHPFlow does not connect to RabbitMQ or resolve transport DSNs.

### Commit 16 fix — PHP package configuration

Messenger routing is now also detected from PHP files in `config/packages/`.

Supported static PHP form:

```php
return App::config([
    'framework' => [
        'messenger' => [
            'routing' => [
                App\Message\GenerateExport::class => 'async',
            ],
        ],
    ],
]);
```

PHPFlow parses the configuration AST and never executes the target project's PHP configuration.

### Commit 16 fix v4 — Symfony typed PHP configurator

PHPFlow now understands the typed Symfony configurator DSL:

```php
return static function (FrameworkConfig $frameworkConfig): void {
    $messengerConfig = $frameworkConfig->messenger();

    $messengerConfig->routing(MyMessage::class)
        ->senders(['async']);
};
```

Both imported classes and fully-qualified `Foo\Bar::class` expressions are resolved statically through `nikic/php-parser`.

### Commit 16 fix v5 — interface-based Messenger routing

Messenger routing declared on an interface is now inherited by concrete messages implementing that interface.

Example:

```php
$messengerConfig->routing(EventInterface::class)
    ->senders(['cred_event']);
```

If `UserCreated implements EventInterface`, PHPFlow resolves `UserCreated` to `cred_event`.

### Commit 16 fix v6 — visible resolved routing

`scan` now separates raw Messenger routing rules from concrete resolved message routing.
This makes interface-based rules visible both as configuration and as their effective routing on dispatched messages.

### Commit 16 fix v7

Messenger diagnostics now expose declared transports, routing rule source files, and preserve multiple rules for the same message/interface instead of silently overwriting them.

### Commit 16 fix v8 — conditional transport configuration

Transport declarations are no longer overwritten by environment-specific overrides.
PHPFlow records both the default DSN and simple conditions such as `if ('test' === $env)`.

## Commit 17

Mermaid exports are now visually differentiated:

- routes use rounded nodes;
- controller methods use rectangles;
- messages use hexagon-like nodes;
- handlers use subroutine nodes;
- synchronous dispatches use solid arrows;
- asynchronous dispatches use dotted arrows and keep their transport label.

This makes route subgraphs much easier to read without changing the generic graph model.

### Commit 17 fix — dark Mermaid theme

Mermaid exports now default to a dark theme with high-contrast text and distinct node colors:
- route: purple
- controller: blue
- message: green
- handler: orange

Edge labels also use a dark background and light text for readability.

### Commit 17 fix v3 — visual language and legend

Nodes now include lightweight Unicode pictograms and every Mermaid export contains a self-contained legend for node types and synchronous/asynchronous links.

## Commit 18

Mermaid labels are now presentation-friendly while the graph keeps full identifiers internally.

Examples:

- `Example\Catalog\Ui\Controller\CatalogController::exportRecords`
  becomes `CatalogController::exportRecords`
- `Example\Catalog\App\Query\ExportRecords`
  becomes `ExportRecords`
- `Example\Catalog\App\Handler\ExportRecordsHandler::__invoke`
  becomes `ExportRecordsHandler::__invoke`

Only rendering labels are shortened. Node IDs and graph relationships still use the full names.

## Commit 19

Recursive Messenger flows are now treated as a first-class feature.

PHPFlow can follow chains such as:

`Controller -> Message -> Handler -> Message -> Handler -> ...`

Cycles are preserved in the graph and highlighted in Mermaid with a red border instead of being removed or causing infinite traversal.

## Commit 20

PHPFlow now detects calls to injected repository services and exposes them as side effects in the graph.

Example:

`PersistCompanyHandler::__invoke -> save -> CompanyRepository`

Repository detection is intentionally conservative and currently relies on repository-like class/interface names.

## Commit 21

PHPFlow now detects direct calls to injected HTTP clients and represents external HTTP effects in the graph.

Supported first-pass pattern:

```php
$this->httpClient->request('POST', 'https://api.example.test/export');
```

Static HTTP methods and URLs are displayed. Dynamic expressions are kept as unknown rather than guessed.

## Commit 22

PHPFlow follows injected service calls across interfaces to unique project implementations.
`#[Autowire(service: ...)]` overrides declared injection types for resolution, and
`#[Autowire(param: ...)]` values are preserved symbolically when reconstructing static HTTP URLs.

Example:
`Handler -> ExternalClientInterface::register -> ExternalClient::register -> POST %base_url%/v1/resources`.

## Commit 23

Repository side effects are represented as callable nodes, for example
`CompanyRepositoryInterface::findRequired` and `CompanyRoutingRepositoryInterface::insert`.

Generic service-call detection now skips calls already modeled by specialized analyzers,
notably Messenger `MessageBusInterface::dispatch`, avoiding duplicate branches in `inspect`.

## Commit 24

PHPFlow now preserves source order for outgoing actions detected in the same method.

Repository calls, service calls, HTTP calls and message dispatches carry AST source positions.
Graph edges use those positions as ordering metadata, and `inspect` traverses outgoing edges
in source order.

This makes flows read like the original PHP method rather than being grouped by detector type.

### Commit 24 Mermaid fix

Mermaid edge declarations now preserve AST source order within each source node.
This gives Mermaid the same ordering hints as `inspect`, while keeping independent
source-node groups stable and without adding visible sequence numbers.

## Commit 25

Resolved service implementations are now real graph nodes instead of controller-like placeholders.

Effects discovered inside an implementation method (repository, HTTP, Messenger or another
service call) attach to that implementation node, so traversal naturally continues:

`Handler -> Interface::method -> ConcreteService::method -> HTTP/repository/message/...`

The existing graph traversal and subgraph extraction cycle guards still prevent infinite
recursion when service calls form cycles.

### Commit 25 fix — Symfony PHP service aliases

Service resolution no longer treats an interface as its own implementation.
PHPFlow now parses PHP service configuration statically and resolves common Symfony alias forms:

- `$services->alias(Interface::class, Concrete::class)`
- `$services->set(Concrete::class)->alias(Interface::class)`

Resolution priority is explicit Symfony alias, analyzer-known implementation, then unique implementation.

### Commit 25 fix v3 — App::config service aliases

PHPFlow now understands Symfony PHP service aliases expressed through `App::config([...])`,
including aliases that point to named service ids backed by an explicit `class`.

Example:

```php
return App::config([
    'services' => [
        'app.external_client' => [
            'class' => ExternalClient::class,
        ],
        ExternalClientInterface::class => service('app.external_client'),
    ],
]);
```

The existing fluent `$services->alias(...)` syntax remains supported.

### Commit 25 final fix

The implementation index now excludes subinterfaces from concrete implementation candidates.
Regression coverage uses the same Symfony PHP pattern as the target project:
`services()->defaults()->autowire()->autoconfigure()`, `load(...)`, and explicit `alias(...)`.

A unique concrete class implementing an interface is now asserted directly at the ProjectIndex level.

### Commit 25 final import-resolution fix

Project indexing now uses fully resolved names for implemented interfaces, parent interfaces,
and traits. This fixes the real-world case where an implementation lives in another namespace
and imports the interface with `use ...`.

Regression coverage now includes:

`App\ImportedResolution\Infra\ConcreteExternalClient`
implements imported
`App\ImportedResolution\Domain\ExternalClientInterface`.

### Commit 25 — production implementation preference

When an interface has multiple implementations, PHPFlow now keeps all candidates but,
for the default project analysis, prefers a unique implementation outside test directories.

For example:

- `src/Infra/RealClient.php`
- `tests/MockClient.php`

resolves to `RealClient`.

If multiple non-test implementations remain, the service stays unresolved instead of guessing.
Explicit Symfony aliases still take precedence over this fallback.

## Commit 26

Repository interfaces now use the same implementation-resolution strategy as generic services.

Resolution priority:
1. explicit Symfony alias;
2. unique non-test implementation;
3. concrete repository itself;
4. unresolved when ambiguity remains.

The graph now expands:

`RepositoryInterface::method -> ConcreteRepository::method`

and concrete repository methods can themselves become sources of further detected effects.

## Commit 27 — database effects

PHPFlow detects statically visible persistence effects in concrete repository/service methods.

Initial support:
- DBAL-style `insert`, `update`, `delete`
- literal SQL passed to `executeStatement`, `executeQuery`, `fetchAssociative`,
  `fetchAllAssociative`, `fetchOne`, or `prepare`
- basic ORM-style `persist`, `remove`, `find`, `findOneBy`, `findBy`

The graph emits database nodes such as `INSERT company` or `UPDATE company`, and includes
the SQL text only when it is statically explicit. PHPFlow does not invent generated SQL.

### Commit 27 final display fix

Database effects are now rendered at architectural level only:

- `SELECT company`
- `INSERT record_links`
- `UPDATE company`
- `DELETE company`

Literal SQL remains available internally on `DatabaseEffect` for future diagnostics/export,
but `inspect` and Mermaid no longer print the complete statement.

Doctrine DBAL/ORM calls already understood as database effects are also excluded from generic
service-call branches, avoiding duplicate nodes such as `Connection::executeStatement`.

## Commit 28 — QueryBuilder database effects

PHPFlow now tracks DBAL QueryBuilder variables created with `Connection::createQueryBuilder()`.

Supported first-pass patterns:
- `$qb->update('table')->...; $qb->executeStatement()` -> `UPDATE table`
- `$qb->delete('table')->...; $qb->executeStatement()` -> `DELETE table`
- `$qb->insert('table')->...; $qb->executeStatement()` -> `INSERT table`
- `$qb->select(...)->from('table', ...); $qb->executeQuery()` -> `SELECT table`

Only architectural operation/table information is emitted in `inspect` and Mermaid.

## Commit 29 — application side effects

PHPFlow now models common non-HTTP/non-database side effects:

- Symfony Mailer: `send()` -> `SEND EMAIL`
- Symfony Filesystem: write/append/delete/move/copy/mkdir/touch
- PSR/Symfony Cache: get/delete/save/clear

Recognized effects replace generic service-call branches and preserve AST source order.
`inspect` and Mermaid keep the representation architectural instead of exposing implementation noise.

## Commit 30 — explicit exceptions

PHPFlow now detects explicit `throw new SomeException(...)` expressions and adds them to
the flow graph in PHP source order. `inspect` shows them as `throws SomeException`, while
Mermaid uses a dedicated exception node and legend entry.

This first pass intentionally does not infer exceptions from PHPDoc, called methods,
framework metadata, or catch/rethrow semantics.

## Commit 31 — conditional exception branches

Explicit exceptions inside `if` blocks are now attached to their syntactic condition.
The graph renders a condition node before the exception:

`IF condition -> throws Exception`

Nested `if` conditions are combined with `&&`. PHPFlow deliberately preserves the PHP
expression as source syntax; it does not attempt symbolic evaluation or infer whether a
condition is reachable.

## Commit 32 — object return values

PHPFlow now detects concrete object values returned by methods, for both `return new Dto()`
and variables previously assigned with `new Dto()`. They are rendered as `returns Dto`
nodes in source order.

This first pass deliberately ignores scalar/array returns and does not yet infer a return
solely from a PHP return type declaration. That keeps the graph focused on concrete values
actually returned by the analyzed code.

## Commit 33 — route summary

`inspect` now supports a compact summary mode:

```bash
php bin/phpflow inspect /workspace /route POST --summary
```

The summary is built from the traversed route graph, so unrelated project effects are not
included. It groups HTTP calls, database effects, mail, filesystem, cache, exceptions and
object return values while hiding controllers, handlers, interfaces and implementation
details.

The normal detailed tree remains the default output.

## Commit 34 — consolidation

This commit stabilizes the feature set accumulated through commits 19–33 rather than adding
a new analyzer family.

Highlights:
- `make inspect ... SUMMARY=1` now forwards `--summary` officially;
- a complete regression fixture covers route -> message -> handler -> repository/service
  resolution -> database/HTTP effects -> conditional exception -> object return;
- a summary regression test protects the architectural output from accidental future noise;
- command wiring and Makefile behavior remain covered by dedicated tests.

The goal is to establish a stable baseline before expanding PHPFlow again.

## Commit 35 — HTTP responses and statuses

PHPFlow now distinguishes outgoing HTTP calls from HTTP responses returned by controllers.

Detected first-pass patterns:
- `new JsonResponse(..., status)` (default 200)
- `new Response(..., status)` (default 200)
- `new RedirectResponse(..., status)` (default 302)
- common Symfony `Response::HTTP_*` constants
- controller helpers `$this->json()`, `$this->redirect()`, `$this->redirectToRoute()`

HTTP response nodes are displayed separately in the graph and under `RESPONSES` in
`inspect --summary`. They are not duplicated as generic object return values.

## Commit 36 — syntactic business branches

PHPFlow now derives branch context from AST parent links instead of a traversal-time condition stack.

First-pass branch labels:
- `IF <expr>`
- `ELSEIF <expr>`
- `ELSE`
- `MATCH <arm>` / `MATCH default`

Conditional exceptions, HTTP responses and concrete object returns are nested under their
branch node. Nested branches are represented as a slash-separated syntactic path.
PHPFlow still does not evaluate conditions or decide which branch is reachable.

## Commit 37 — guard clauses and nominal continuation

PHPFlow now recognizes a conservative guard-clause pattern:

- a top-level `if` inside a method;
- no `elseif` or `else`;
- the branch terminates with `return` or `throw`;
- statements follow the `if`.

Effects after that guard are grouped under a `CONTINUE` node. This distinguishes the
early-exit path from the nominal path without attempting to build a complete PHP CFG.

Example:

`IF !$valid -> HTTP 400`, alongside `CONTINUE -> HTTP 200`.

## Commit 38 — try / catch / finally control branches

PHPFlow now records source ranges for `TRY`, each `CATCH <type>`, and `FINALLY`.
During graph construction, any ordered effect whose AST position falls inside one of those
ranges is re-parented under the corresponding control-branch node.

This makes the mechanism generic: HTTP responses, service/repository calls, database effects,
explicit exceptions and return values can all be grouped without each effect type knowing
about try/catch semantics.

PHPFlow still does not infer which exception a call may throw or which catch will run.

## Commit 39 — conditional non-terminal effects

PHPFlow now records source ranges for `IF`, `ELSEIF`, `ELSE` and `MATCH` arms as
effect-only control branches.

Non-terminal architectural effects inside those ranges (message dispatches, repository and
service calls, outgoing HTTP calls, database effects, mail/filesystem/cache effects) are
re-parented under the matching branch node.

Terminal nodes already modeled by earlier commits (`throw`, object return, HTTP response,
guard continuation) are explicitly excluded from this generic re-parenting to avoid duplicate
branch nodes.

Branch nodes are only emitted when at least one eligible effect is actually moved.

## Commit 40 — loop control branches

PHPFlow now models `foreach`, `for`, `while` and `do ... while` as structural loop nodes.

Loop labels preserve the PHP syntax, for example:

- `FOREACH $items as $key => $item`
- `FOR $i = 0; $i < 3; ++$i`
- `WHILE $running`
- `DO WHILE $running`

Unlike effect-only IF branches, loops are structural: nested conditions, returns, exceptions
and architectural effects can all remain under the loop. Effects after the loop stay on the
nominal method path.

PHPFlow never estimates or expands the number of iterations.

## Commit 41 — break and continue

PHPFlow now models explicit loop-control statements:

- `continue` -> `CONTINUE LOOP`
- `break` -> `BREAK`
- numeric levels are preserved (`break 2` -> `BREAK 2`)

When the statement is inside an `if`/`elseif`/`else`/`match` branch, the loop-control node is
placed under that syntactic condition. The enclosing loop branch from commit 40 then groups
the whole structure naturally.

This is descriptive only: PHPFlow does not remove subsequent AST effects as unreachable and
does not simulate loop iterations.

## Commit 42 — expression-level branches

PHPFlow now models effectful expression branches:

- ternary `condition ? a : b`
- null coalescing `left ?? fallback`
- short-circuit `left && effect`
- short-circuit `left || effect`

Labels stay syntactic and descriptive (`TERNARY ... THEN`, `COALESCE ... IS NULL`,
`IF ...`, `IF NOT (...)`). These are effect-only control branches, so terminal nodes already
handled elsewhere remain untouched.

PHPFlow does not evaluate truthiness, nullability, or expression results.

## Commit 43 — unreachable code filtering

PHPFlow now filters architectural effects that occur after an unconditional terminating
statement in the same block:

- `return`
- explicit `throw`
- `break`
- `continue`

The analysis is block-local and conservative. Nested `if`, loop, `try`, `catch`, and
`finally` statement lists are checked independently. PHPFlow does not yet prove that an
entire `if/else` construct is terminating on all branches.

The filtering happens before `ProjectAnalysis` is returned, so unreachable calls do not leak
into scans, summaries, graphs, or Mermaid output.

## Commit 44 — composed branch termination

Reachability now understands a fully terminating `if / elseif / else` construct.

An `if` statement terminates its current block when:
- it has an `else`;
- the `if` branch terminates;
- every `elseif` branch terminates;
- the `else` branch terminates.

This allows PHPFlow to remove effects that follow constructs such as
`if (...) { return; } else { throw ...; }`.

The analysis remains conservative: an `if` without `else`, or any branch that can fall
through, does not terminate the surrounding block.

## Commit 45 — composed try/catch/finally termination

Reachability now understands terminating `try / catch / finally` structures.

A `try/catch` terminates its surrounding block when the `try` block terminates and every
`catch` block terminates. A terminating `finally` is stronger: when `finally` always
terminates, the whole construct terminates regardless of whether the `try` or catches can
fall through.

The analysis stays conservative when any catch can continue.

## Commit 46 — terminating match reachability

PHPFlow now understands an expression-statement `match` as terminating when:

- the match has a `default` arm (syntactically exhaustive);
- every arm terminates;
- terminating arm expressions are currently explicit `throw` expressions or nested
  terminating `match` expressions.

A non-exhaustive match, or any arm that can produce a normal value/effect, remains
conservatively non-terminating.

## Publication hygiene

The bundled examples and fixtures use fictional namespaces, routes, service names, URLs,
and database tables. Before publishing a fork or derived repository, it is still recommended
to scan both the working tree and Git history for organization-specific names, credentials,
absolute local paths, private hostnames, and project identifiers.
