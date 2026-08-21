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
```

Equivalent direct command:

```bash
php bin/phpflow impact:message /path/to/project 'App\\Message\\SyncCompany'
```

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
