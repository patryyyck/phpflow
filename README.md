# PHPFlow

PHPFlow is a static flow analyzer for PHP applications. It reconstructs application paths
from framework entry points to messages, handlers, services, repositories, database effects,
external HTTP calls, exceptions, and HTTP responses — without executing the target project.

> **Repository description:** Static flow analyzer for PHP applications — trace routes through messages, services, database effects and external HTTP calls without running the app.

## Why PHPFlow?

Large PHP applications often hide an application's real execution path across controllers,
Messenger messages, handlers, interfaces, repositories, framework configuration, and
infrastructure clients. PHPFlow turns those relationships into an inspectable flow graph.

It is designed to help answer questions such as:

- What happens when this HTTP route is called?
- Which messages and handlers are involved?
- Which repositories and database tables can be reached?
- Which external HTTP APIs can be called?
- Which HTTP responses or exceptions can terminate the flow?
- Where does a recursive or cyclic service/message path lead?

PHPFlow performs static analysis only. It does not boot or execute the application being
analysed.

## Example

Given a fictional application flow, PHPFlow can render an inspection tree similar to:

```text
POST /catalog/{recordId}/sync
└── CatalogController::sync
    └── SyncRecord
        └── SyncRecordHandler::__invoke
            ├── RecordRepositoryInterface::findRequired
            │   └── RecordRepository::findRequired
            │       └── SELECT records
            ├── ExternalSyncClientInterface::sync
            │   └── ExternalSyncClient::sync
            │       └── POST %sync.base_url%/v1/resources
            └── RecordLinkRepositoryInterface::insert
                └── RecordLinkRepository::insert
                    └── INSERT record_links
```

The exact output depends on what PHPFlow can prove statically from the target project's
source and configuration.

## Features

PHPFlow currently supports:

- recursive discovery of PHP source files while excluding `vendor/` from application scans;
- PHP declarations and PHP 8 attributes;
- Symfony `#[Route]` controller routes;
- Symfony Messenger dispatches, handlers, recursive message flows, and cycles;
- Messenger routing from YAML and PHP configuration, including interface-based routing;
- injected service calls and interface-to-implementation resolution;
- Symfony service aliases declared in PHP configuration;
- repository calls and DBAL/database effects;
- database table extraction for common `SELECT`, `INSERT`, `UPDATE`, and `DELETE` patterns;
- Doctrine DBAL QueryBuilder effects;
- external HTTP client calls with statically recoverable methods and URLs;
- source-order preservation for effects;
- HTTP responses and status codes;
- conditional branches, `match`, ternaries, `??`, and short-circuit expressions;
- thrown exceptions and `try/catch/finally`;
- loops plus `break` and `continue`;
- conservative unreachable-code filtering;
- human-readable flow trees and compact `--summary` output;
- complete or route-scoped Mermaid graph export.

## Requirements

The recommended development setup requires:

- Docker with Docker Compose;
- GNU Make.

The PHP package itself requires PHP 8.4.

## Installation

Clone the repository and build the development container:

```bash
git clone git@github.com:YOUR_GITHUB_USERNAME/phpflow.git
cd phpflow

make build
docker compose run --rm php composer install
```

Run the test suite:

```bash
make test
```

## Quick start

Scan a project:

```bash
make scan PROJECT_PATH=/path/to/project
```

Equivalent direct command:

```bash
docker compose run --rm \
    -v "/path/to/project:/workspace:ro" \
    php php bin/phpflow scan /workspace
```

Inspect a route:

```bash
make inspect \
    PROJECT_PATH=/path/to/project \
    ROUTE='/catalog/{recordId}/sync' \
    METHOD=POST
```

For a compact view:

```bash
make inspect \
    PROJECT_PATH=/path/to/project \
    ROUTE='/catalog/{recordId}/sync' \
    METHOD=POST \
    SUMMARY=1
```

## Impact analysis

PHPFlow also exposes a unified impact command. Choose exactly one target:

```bash
php bin/phpflow impact /path/to/project --table=companies
php bin/phpflow impact /path/to/project --http='/v2/directory/search'
php bin/phpflow impact /path/to/project --message=SyncCompany
php bin/phpflow impact /path/to/project --service='InvoiceGenerator::generate'
php bin/phpflow impact /path/to/project --exception=PaymentFailed
```

Use `--summary` to list only the unique impacted entry points. The existing specialized
`impact:*` commands remain available.


Impact results can also be emitted as a dedicated versioned JSON contract:

```bash
php bin/phpflow impact /path/to/project \
    --table=companies \
    --format=json
```

Write the focused blast radius to a file:

```bash
php bin/phpflow impact /path/to/project \
    --service='InvoiceGenerator::generate' \
    --format=json \
    --output=/tmp/phpflow-impact.json
```

The impact JSON contract starts at `schemaVersion: "1.0"` and contains the search `target`,
unique `entryPoints`, structured `nodes`, and each complete impact `path` as node IDs. It is
designed for CI integrations and focused views in the future interactive PHPFlow UI.

With the Makefile, the same facade is available through `make impact`, for example:

```bash
make impact PROJECT_PATH=/path/to/project SERVICE='InvoiceGenerator::generate' SUMMARY=1
```

PHPFlow can also traverse the graph backwards from a database table to the application entry
points that can reach it. Entry points include HTTP routes and standalone Messenger messages.

```bash
make impact-table \
    PROJECT_PATH=/path/to/project \
    TABLE=record_links
```

Equivalent direct command:

```bash
php bin/phpflow impact:table /path/to/project record_links
```

Example:

```text
Entry points impacting table record_links

POST /catalog/{recordId}/sync
└── CatalogController::sync
    └── SyncRecord
        └── SyncRecordHandler::__invoke
            └── RecordLinkRepositoryInterface::insert
                └── RecordLinkRepository::insert
                    └── INSERT record_links
```

Table lookup accepts both bare and schema-qualified names. For example, `TABLE=companies`
matches effects on `companies`, `public.companies`, or quoted SQL identifiers such as
`"public"."companies"`.

You can restrict the result to one SQL operation:

```bash
make impact-table \
    PROJECT_PATH=/path/to/project \
    TABLE=companies \
    OPERATION=SELECT
```

Equivalent direct command:

```bash
php bin/phpflow impact:table /path/to/project companies --operation=SELECT
```

Supported filters are `SELECT`, `INSERT`, `UPDATE`, and `DELETE`.


Standalone Messenger messages are treated as impact entry points when they are not already
dispatched from another modelled route or process. This lets `impact:table` and `impact:http`
report worker/consumer flows in addition to HTTP routes without duplicating messages that
already belong to a route-driven path.

Messenger messages can also be searched in reverse to find which entry points can dispatch
them, including recursive message-to-message flows:

```bash
make impact-message \
    PROJECT_PATH=/path/to/project \
    MESSAGE='App\\Message\\SyncCompany'
```

A short class name is accepted when convenient:

```bash
make impact-message PROJECT_PATH=/path/to/project MESSAGE=SyncCompany
make impact-service PROJECT_PATH=/path/to/project SERVICE=InvoiceGenerator
make impact-exception PROJECT_PATH=/path/to/project EXCEPTION=PaymentFailed
```

Equivalent direct command:

```bash
php bin/phpflow impact:message /path/to/project 'App\\Message\\SyncCompany'
```

Application classes and methods can be searched to estimate the entry points affected by a
code change:

```bash
make impact-service \
    PROJECT_PATH=/path/to/project \
    SERVICE='App\Service\InvoiceGenerator'
```

Target one method:

```bash
make impact-service \
    PROJECT_PATH=/path/to/project \
    SERVICE='App\Service\InvoiceGenerator::generate'
```

Short names such as `InvoiceGenerator` and `InvoiceGenerator::generate` are also accepted.
The search includes services, repositories, handlers, and controllers represented in the
flow graph.

Thrown exceptions can be searched in reverse to identify every route or standalone
Messenger process that can reach the corresponding `throw`:

```bash
make impact-exception \
    PROJECT_PATH=/path/to/project \
    EXCEPTION='App\Exception\PaymentFailed'
```

Short exception class names are also accepted:

```bash
make impact-exception \
    PROJECT_PATH=/path/to/project \
    EXCEPTION=PaymentFailed
```

Conditional branches leading to the exception remain visible in the returned path.

External HTTP endpoints can be searched by full URL or by fragment:

```bash
make impact-http \
    PROJECT_PATH=/path/to/project \
    HTTP='/v1/resources'
```

Equivalent direct command:

```bash
php bin/phpflow impact:http /path/to/project '/v1/resources'
```

HTTP URL reconstruction is performed directly from the AST when full string resolution is not possible. Partially dynamic URLs preserve their known static fragments using `{dynamic}` placeholders, for example `POST {dynamic}/v2/directory/search`. This keeps `impact:http` useful even when a base URL or path parameter cannot be fully resolved.

The HTTP lookup is case-insensitive and matches against the complete effect label, including
the HTTP method and statically recovered URL. Both table and HTTP impact analysis now share
the same cycle-safe reverse graph traversal.

## Graph comparison foundation

PHPFlow can compare two versioned graph JSON payloads through its application layer. This
foundation is intentionally separate from the CLI for now: it detects added and removed
nodes, changed node payloads, added and removed edges, and produces a compact summary for
routes, database effects, external HTTP calls, messages, exceptions, services, repositories,
handlers, and controllers.

A node whose stable ID remains the same but whose exported payload changes is represented as
one removed node plus one added node. This makes semantic changes such as `SELECT companies`
becoming `UPDATE companies` visible instead of silently treating the node as unchanged.

The comparison layer is exposed through the CLI:

```bash
php bin/phpflow diff before.json after.json
```

The text report includes a compact category summary followed by added/removed nodes and
edges. Invalid or unreadable input files fail explicitly instead of producing a partial diff.

For CI and pull-request automation, the same comparison can be emitted with the versioned
diff JSON schema `1.0`:

```bash
php bin/phpflow diff before.json after.json --format=json
php bin/phpflow diff before.json after.json --format=json --output=/tmp/phpflow-diff.json
```

The Makefile wrapper forwards the format and persists JSON output outside the container:

```bash
make diff BEFORE=/path/before.json AFTER=/path/after.json
make diff BEFORE=/path/before.json AFTER=/path/after.json FORMAT=json
make diff BEFORE=/path/before.json AFTER=/path/after.json FORMAT=json OUTPUT=/tmp/phpflow-diff.json
```

The machine-readable payload contains `hasChanges`, the category `summary`, and separate
`added`/`removed` node and edge collections. This keeps CI consumers independent from the
human-readable text renderer.

## Interactive HTML export

PHPFlow can generate a self-contained interactive HTML viewer. No application server or
external JavaScript dependency is required: the versioned graph JSON is embedded directly
in the generated file.

```bash
make export-html \
    PROJECT_PATH=/path/to/project \
    HTML_OUTPUT=/tmp/phpflow.html
```

A route can be isolated just like the JSON and Mermaid exports:

```bash
make export-html \
    PROJECT_PATH=/path/to/project \
    HTML_OUTPUT=/tmp/phpflow-route.html \
    ROUTE=/companies \
    METHOD=GET
```

Open the generated file in a browser. The viewer supports pan/zoom, fit/reset, filtering by
node type, node selection, direct-connection counts, and inspection of the structured
metadata introduced by JSON schema `1.2`.

Branches can be collapsed and expanded directly from graph nodes. From the details panel,
you can focus the selected branch, highlight only its direct connections, jump back to the
nearest application entry point, or clear the current focus. These controls are especially
useful when exploring large route and Messenger flows.


The viewer also includes full-graph search. Search by route, short or fully-qualified class
name, method, table, HTTP URL, message, exception, node type, or any structured metadata.
Matching nodes are highlighted in the graph; selecting a result automatically reveals
collapsed ancestors, restores a filtered node type when necessary, centers the node, and
opens its details. Search also indexes incoming and outgoing edge context, so dynamically
resolved external HTTP URLs remain discoverable even when the HTTP endpoint node itself
contains a parameter placeholder. Press Enter to open the first result or Escape to clear
the search.


Exploration presets make large graphs easier to scan without changing the exported graph:
**Entry points** shows application roots, **Database** shows database effects, **External HTTP**
shows outbound HTTP endpoints, and **Errors** focuses exceptions plus HTTP 4xx/5xx responses.
The **Hide technical nodes** toggle removes control-flow-only nodes such as conditions, loop
controls, continuations, and return values. The existing node-type checkboxes remain available
and combine with these presets.


The graph layout is hierarchical and deterministic. Nodes inside each depth level are reordered
with repeated barycentric passes against their parents and children, which keeps related branches
closer together and reduces edge crossings on large flows. Levels are vertically balanced and
use adaptive spacing for dense graphs. Connections are drawn as smooth horizontal curves instead
of straight diagonals, making long branches easier to follow.

The viewer remains self-contained and dependency-free: all navigation runs locally in the
generated HTML file.

## JSON export

PHPFlow can export the same flow graph as a stable, versioned JSON contract. This format is
intended for integrations and future interactive graph visualizations without coupling them
to PHPFlow's internal PHP objects.

```bash
make export-json \
    PROJECT_PATH=/path/to/project \
    JSON_OUTPUT=/tmp/phpflow.json
```

Export only one route:

```bash
make export-json \
    PROJECT_PATH=/path/to/project \
    JSON_OUTPUT=/tmp/phpflow.json \
    ROUTE=/companies \
    METHOD=GET
```

The top-level `schemaVersion` identifies the contract version:

```json
{
    "schemaVersion": "1.2",
    "nodes": [
        {
            "id": "route:GET:/companies",
            "type": "route",
            "label": "GET /companies",
            "metadata": {
                "entryPoint": true,
                "route": {
                    "method": "GET",
                    "path": "/companies"
                }
            }
        }
    ],
    "edges": []
}
```

Nodes expose both their canonical `label` and a UI-oriented `displayLabel`. Callable,
message, and exception nodes keep the full namespace in `label` while `displayLabel` uses
the short class name for readability. Structured metadata includes `class`, `shortName`,
`namespace`, method when relevant, and the real indexed source `file` when PHPFlow knows it.
Route, database, and HTTP metadata remain structured as before. Every node also exposes an
`entryPoint` boolean so consumers do not need to infer application roots from graph topology.

Edges expose `source`, `target`, and `type`, plus `label`, source `order`, and propagated
`context` when those values exist. Consumers should use `schemaVersion` rather than relying
on PHPFlow's internal object structure. Schema `1.2` remains additive: canonical labels and IDs are unchanged: the original
`id`, `type`, and `label` fields remain unchanged.

## Mermaid export

Export the complete graph:

```bash
make export-mermaid \
    PROJECT_PATH=/path/to/project \
    MERMAID_OUTPUT=/tmp/phpflow.mmd
```

Export only the graph reachable from one route:

```bash
make export-mermaid \
    PROJECT_PATH=/path/to/project \
    ROUTE='/catalog/{recordId}/sync' \
    METHOD=POST \
    MAX_DEPTH=10 \
    MERMAID_OUTPUT=/tmp/phpflow.mmd
```

The generated `.mmd` file can be rendered by any Mermaid-compatible tool.

## How it works

PHPFlow parses PHP source and selected Symfony configuration statically. The analysis builds
a framework-independent graph whose nodes represent application concepts such as routes,
methods, messages, handlers, database effects, and HTTP effects.

The graph can then be traversed from a route or exported independently of the Symfony-specific
analysis that produced it.

PHPFlow deliberately prefers an unresolved or unknown result over guessing.

## Limitations

PHPFlow is a static analyzer, not a PHP runtime. In particular:

- dynamic class names, dynamic method names, reflection, and runtime container manipulation
  may not be resolvable;
- URLs, SQL, and table names assembled from values that cannot be recovered statically may
  remain unknown;
- service resolution is intentionally conservative when multiple implementations are
  possible and configuration does not disambiguate them;
- reachability analysis models supported syntax conservatively and is not a general theorem
  prover;
- framework configuration generated dynamically at runtime may not be understood;
- PHPFlow does not connect to databases, message brokers, or external HTTP services.

A missing edge therefore means "PHPFlow could not prove this relationship", not necessarily
"this relationship can never occur at runtime".

## Development

Install dependencies and run tests:

```bash
docker compose run --rm php composer install
make test
```

Useful commands:

```bash
make scan PROJECT_PATH=/path/to/project
make inspect PROJECT_PATH=/path/to/project ROUTE=/route METHOD=GET
make inspect PROJECT_PATH=/path/to/project ROUTE=/route METHOD=GET SUMMARY=1
make impact-message PROJECT_PATH=/path/to/project MESSAGE=SyncCompany
make export-mermaid PROJECT_PATH=/path/to/project
```

## Continuous integration

The repository includes a GitHub Actions workflow that installs Composer dependencies and
runs the PHPUnit suite on pushes and pull requests.

After the repository is published, replace `YOUR_GITHUB_USERNAME` in the clone example and
badge URL with the actual GitHub account name.

## License

PHPFlow is open source software licensed under the [MIT License](LICENSE).


### Contextual HTTP wrapper resolution

When an application service forwards an HTTP method and URL through a wrapper such as
`ClientInterface::request($method, $url, ...)`, PHPFlow carries statically recoverable
arguments along the inspected call path. An implementation-level call using `$method` and
`$url` can therefore render the caller-specific endpoint instead of `HTTP <dynamic URL>`.

Service implementation resolution prefers explicit Symfony aliases and production
implementations. Test-only implementations are not selected as fallback implementations.


### Symfony environment-specific service aliases

By default, PHPFlow reads the base `config/services.php` configuration and ignores
environment-specific overrides such as `services_test.php`. This prevents test mocks from
silently replacing production implementations during a normal inspection.

The alias reader can still be asked explicitly for an environment when needed; in that case,
for example, `services_test.php` is applied after the base configuration.


Class string constants such as `self::TOKEN_ENDPOINT` are resolved when their value is a
static string. This allows URLs like `%auth.base_url%/oauth/token` to remain fully visible
instead of degrading to `{dynamic}`.


### Union-typed Messenger handlers

A Messenger handler whose first argument is a named union type, for example
`FirstCommand|SecondCommand $command`, is registered for every named member of the union.
This allows each dispatched message to continue through the same handler in the flow graph.


### Same-class method calls

PHPFlow follows calls from one method to another method of the same class, including private
helpers. This is important for infrastructure clients where a public method delegates to a
private pagination/fetch method that performs the actual HTTP or database effect.


### Static `sprintf()` URL construction

PHPFlow resolves simple `sprintf()` calls when the format string is static. Known arguments
are substituted and unresolved arguments become `{dynamic}` placeholders. This allows
patterns such as `sprintf('%s/v2/directory/search', $this->baseUrl)` to remain visible in
HTTP effects and impact analysis.


HTTP impact lookup resolves path-specific call context before matching. This means a raw
graph node such as `{param:method} {param:url}` can still be found by `impact:http` when an
upstream call supplies a concrete method and URL for that route.
