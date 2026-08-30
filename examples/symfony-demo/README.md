# PHPFlow Symfony demo

This directory is a **synthetic Symfony application designed for static analysis**.

It intentionally packs many independent patterns into one codebase so that a new PHPFlow user can
explore the v0.1 feature surface without pointing the analyzer at proprietary code. The domain is
fictional and the application is not intended to model production architecture.

From a fresh PHPFlow clone:

```bash
make setup
make demo
```

`make demo` scans this project and writes `/tmp/phpflow-demo.html`. If PHPFlow is already set up,
`make demo-scan` and `make demo-html` remain available separately.

## Recommended starting points

These flows are useful when discovering the viewer:

| Entry point | What it demonstrates |
| --- | --- |
| `GET /users/{id}` | Route/controller discovery, service/repository calls and HTTP responses. |
| `POST /recursive` | Messenger dispatch and recursive message flow. |
| `GET /try-catch` | `try/catch/finally`, exceptions and multiple HTTP statuses. |
| response routes under `/responses/*` | Response classes and status extraction. |
| branching/early-return routes | Conditions, guards and conservative reachability. |

The project also contains orphan Messenger messages that can become impact-analysis roots.

## Coverage map

The files below are deliberately small and focused. Together they exercise the documented PHPFlow
v0.1 analysis families.

### PHP declarations and call flow

- `src/Contract.php`, `Reusable.php`, `Status.php` — interfaces, traits and enums.
- imported symbols across `src/ImportedResolution/` — FQCN/import resolution.
- `src/LocalMethodHttpFlow.php` — same-class helper calls contributing downstream effects.
- `src/CyclicServiceA.php` and `src/CyclicServiceB.php` — recursive/cyclic service traversal.
- service-call fixtures propagate supported static string context through call chains.

### Symfony routes and responses

- `src/UserController.php` — attribute routes with multiple HTTP methods.
- `src/HttpResponseController.php` — JSON, empty/default and redirect responses with status codes.
- `src/EarlyReturnController.php` — guard/early-return reachability.
- `src/BranchingController.php` — branching controller flow.
- `src/TryCatchController.php` — exceptions, catches, finally effects and 2xx/4xx/5xx responses.
- `src/TraitBasedController.php` — controller behavior contributed by traits.
- `src/VendorBasedController.php` — symbol resolution involving the synthetic vendor fixture.

### Services and dependency injection

- `config/services.php` — autowiring/autoconfiguration and interface aliases.
- `config/services_test.php` — environment-specific service override.
- `src/ExternalSyncClientInterface.php` / `ExternalSyncClient.php` — interface → implementation.
- `src/PreferredServiceImplementation.php` — configured implementation preference.
- `src/ExternalSyncClient.php` and `PartialHttpUrlClient.php` — `#[Autowire]` service/parameter
  references used during static reconstruction.

### Symfony Messenger

- `src/CreateUser.php`, `CreateUserHandler.php` — message and `#[AsMessageHandler]`.
- `src/DeleteUser.php`, `DeleteUserHandler.php` — additional routed message flow.
- `src/RecursiveController.php` plus `FirstMessage*`, `SecondMessage*`, `ThirdMessage*` — recursive
  message traversal and cycle safety.
- `src/UnionMessageHandler.php` — union-typed handler arguments.
- `src/TraitMessage.php` — Messenger `HandleTrait` wrapper pattern.
- `config/packages/messenger.yaml` — YAML routing and multiple senders.
- `config/packages/messenger.php` — PHP configurator routing, transports and interface routing.
- `config/packages/messenger_webhook.php` — an additional routing/transport rule.
- `src/EventInterface.php` / `UserCreated.php` — interface-based routing to a concrete message.

### Doctrine, repositories and SQL effects

- `src/CompanyRepository.php` / `DoctrineCompanyRepository.php` — repository interface and concrete
  implementation.
- direct DBAL reads/writes using literal SQL, local SQL variables and class constants.
- helper-composed SQL from a private method.
- QueryBuilder `SELECT`, `UPDATE` and `DELETE`.
- direct `insert()` and `executeStatement()` effects.
- quoted/schema-qualified identifiers such as `"public"."companies"`.
- `src/PersistCompany*`, `ListCompanies.php` and `PreRegisterCompanies.php` provide entry flows that
  reach repository/database effects.

### External HTTP and static value reconstruction

- `src/ExternalApiHandler.php` — direct statically identifiable HTTP request.
- `src/ExternalSyncClient.php` — URL concatenation with an autowired parameter.
- `src/PartialHttpUrlClient.php` — dynamic fragments, local URL variables, interpolation,
  `self::CONST`, `sprintf()` and parameter-backed base URLs.
- `src/LocalMethodHttpFlow.php` — HTTP calls reached through same-class helper methods.
- `src/DoWhileHttpClient.php` — external HTTP effect inside loop flow.

### Mail, filesystem and cache

- `src/ApplicationEffectsHandler.php` — mail send, filesystem write and cache deletion in one
  application flow.

### Conditions and expression flow

- `src/ConditionalEffectsHandler.php` — `if` / `elseif` / `else`.
- `src/ExpressionBranchHandler.php` — ternary, null coalescing and boolean short-circuit calls.
- `src/ConsolidatedFlow.php` — compact combinations of supported effects/control flow.
- `src/UnreachableEffectsHandler.php` — conservative unreachable-code pruning.

### Exceptions and structured control flow

- `src/ExceptionFlowHandler.php` — nested conditions and multiple thrown exception types.
- `src/TryCatchController.php` — `try/catch/finally`.
- `src/LoopEffectsHandler.php` — `foreach`, `for`, `while`, `do/while`, `break`, `continue` and
  nested break.
- `src/ReturnFlowHandler.php` — return/termination flow.

## Configuration fixtures

The demo includes a tiny synthetic `vendor/` tree on purpose. It exists only to demonstrate the
vendor-symbol resolution patterns that PHPFlow supports and should not be treated as a normal
committed Composer vendor directory.

Likewise, `config/fixtures/test-services/` contains sanitized test-environment implementations used
to illustrate environment-specific alias resolution.

## Static-analysis contract

PHPFlow does not boot this application, connect to its database, send messages, make HTTP requests,
write files, send mail or contact cache infrastructure.

Some intentionally dynamic values remain unresolved. That is part of the demonstration: PHPFlow
prefers an incomplete but provable graph over guessing runtime behavior.

For the exact contract, see [`../../docs/SUPPORT.md`](../../docs/SUPPORT.md).
