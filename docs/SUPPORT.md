# PHPFlow support matrix

This document defines the supported analysis surface for the upcoming `v0.1.0` release.

PHPFlow is a **static analyzer**. It parses source code and selected configuration; it never
boots the target application, executes application code, connects to infrastructure, or
observes runtime traffic.

## Status legend

| Status | Meaning |
| --- | --- |
| **Supported** | Covered by the current analyzer and regression suite for the documented static patterns. |
| **Partial** | Recognized for common/static forms, but dynamic or ambiguous variants may remain unresolved. |
| **Not supported** | Outside the `v0.1` analysis contract. PHPFlow does not claim to infer it. |

"Supported" does not mean that every runtime-equivalent PHP expression is understood. When a
relationship cannot be proven statically, PHPFlow deliberately prefers an unresolved result
over guessing.

## Runtime and project compatibility

| Area | Status | v0.1 contract |
| --- | --- | --- |
| PHP | **Supported** | PHP `^8.4`. |
| Parser | **Supported** | `nikic/php-parser ^5.6`. |
| Symfony components used by PHPFlow | **Supported** | Console, Finder and Yaml `^7.3`. |
| Target project execution | **Not supported** | The target application is never booted or executed. |
| `vendor/` application scanning | **Not supported** | Application source discovery excludes `vendor/`; vendor symbols can still be used for symbol resolution where supported. |

## PHP declarations and call flow

| Pattern | Status | Notes |
| --- | --- | --- |
| Classes, interfaces, traits and enums | **Supported** | Declarations are indexed with resolved names. |
| PHP 8 attributes | **Supported** | Used by framework-specific analyzers where documented below. |
| FQCN/import resolution | **Supported** | Uses parser name resolution rather than string-only matching. |
| Injected service method calls | **Supported** | Static receiver/type resolution is required. |
| Same-class `$this->method()` recursion | **Supported** | Private/helper methods can contribute downstream effects. |
| Recursive service/repository chains | **Supported** | Traversal is cycle-safe. |
| Argument context propagation | **Partial** | Static string argument context is propagated through supported service-call chains. |
| Dynamic class names | **Not supported** | Runtime-only class selection is not guessed. |
| Dynamic method names | **Not supported** | Runtime-only method selection is not guessed. |
| Reflection-driven call graphs | **Not supported** | Reflection is not executed or emulated. |

## Symfony HTTP/controller analysis

| Pattern | Status | Notes |
| --- | --- | --- |
| `#[Route]` controller routes | **Supported** | Route path and HTTP method become graph entry points. |
| Controller call flow | **Supported** | Supported calls/effects are traversed recursively. |
| HTTP response/status extraction | **Supported** | Statically recoverable response types/statuses are represented. |
| Static route-scoped exports | **Supported** | Mermaid, JSON and HTML exports can be scoped by route and depth. |
| Runtime/generated routing configuration | **Not supported** | Routes created only while booting the application are outside the contract. |

## Symfony service resolution

| Pattern | Status | Notes |
| --- | --- | --- |
| Concrete injected services | **Supported** | Resolved from indexed project symbols. |
| Interface → implementation resolution | **Supported** | Uses project index plus supported Symfony aliases. |
| Symfony service aliases in PHP config | **Supported** | Includes supported configurator/static configuration forms. |
| Environment-specific service overrides | **Supported** | Base configuration plus requested environment overrides are considered. |
| `#[Autowire(param: ...)]` parameter references | **Supported** | Parameter references can participate in static value reconstruction. |
| Multiple ambiguous implementations | **Partial** | PHPFlow stays conservative when configuration cannot disambiguate. |
| Runtime container mutation/compiler behavior | **Not supported** | Arbitrary runtime service registration/replacement is not executed. |

## Symfony Messenger

| Pattern | Status | Notes |
| --- | --- | --- |
| Direct `dispatch()` calls | **Supported** | Message dispatches become graph edges. |
| Dispatch through local variables | **Supported** | Covered when the message value remains statically identifiable. |
| Messenger `HandleTrait` wrappers | **Supported** | Supported wrapper calls can resolve to handled messages. |
| `#[AsMessageHandler]` | **Supported** | Handler/message relationships are indexed from handler signatures. |
| Union-typed handler arguments | **Supported** | Supported message alternatives are represented. |
| Recursive message flows | **Supported** | Traversal is cycle-safe. |
| YAML Messenger routing | **Supported** | Static routing rules/transports are parsed. |
| PHP Messenger routing configuration | **Supported** | Supported configurator/App configuration forms are parsed. |
| Interface-based routing | **Supported** | Routing declared for an interface can apply to concrete messages. |
| Multiple routing rules/transports | **Supported** | Declared transport/routing information is preserved. |
| Runtime-generated Messenger configuration | **Not supported** | Configuration available only after application execution is outside the contract. |
| Broker state/delivery outcome | **Not supported** | PHPFlow never connects to a transport or observes message delivery. |

## Doctrine, repositories and database effects

| Pattern | Status | Notes |
| --- | --- | --- |
| Repository calls | **Supported** | Concrete/interface repository resolution is supported for statically resolvable calls. |
| DBAL direct SQL methods | **Supported** | Common direct DBAL operations are classified. |
| DBAL QueryBuilder | **Supported** | Common statically recoverable SELECT/INSERT/UPDATE/DELETE effects are classified. |
| SQL operation classification | **Supported** | SELECT, INSERT, UPDATE and DELETE are represented. |
| Table extraction | **Partial** | Static/simple SQL and QueryBuilder targets are recovered; highly dynamic SQL may remain unknown. |
| Schema-qualified/quoted identifiers | **Supported** | Impact matching handles the supported schema/quoted identifier forms. |
| ORM runtime/unit-of-work behavior | **Not supported** | PHPFlow does not boot Doctrine or infer runtime persistence events beyond recognized static calls. |
| Database contents/schema introspection | **Not supported** | No database connection is made. |

## External HTTP and static value reconstruction

| Pattern | Status | Notes |
| --- | --- | --- |
| External HTTP client effects | **Supported** | Statically identifiable HTTP calls become `http_endpoint` nodes. |
| HTTP method extraction | **Supported** | Static methods are preserved where recoverable. |
| Literal/concatenated URLs | **Supported** | Static concatenation is reconstructed; unknown parts can remain dynamic. |
| Local URL variables | **Supported** | Supported local assignments are followed. |
| `sprintf()` URL construction | **Supported** | Common static `sprintf()` constructions are reconstructed. |
| `self::CONST` values | **Supported** | Supported class constants can contribute to reconstruction. |
| Symfony parameter/autowire values | **Partial** | Supported parameter references are reconstructed when statically available. |
| Arbitrary runtime URL computation | **Not supported** | Values requiring execution remain unresolved. |
| Network outcome/status | **Not supported** | No HTTP request is sent. |

## Other effects

| Effect | Status | Notes |
| --- | --- | --- |
| Mail | **Supported** | Recognized mail effects become graph nodes. |
| Filesystem | **Supported** | Recognized filesystem effects become graph nodes. |
| Cache | **Supported** | Recognized cache effects become graph nodes. |
| Thrown exceptions | **Supported** | Exception nodes can participate in flow and impact analysis. |
| Arbitrary third-party side effects | **Partial** | Only effects with an explicit analyzer/model are represented. |

## Control flow

| Pattern | Status | Notes |
| --- | --- | --- |
| `if` / `elseif` / `else` | **Supported** | Branches are represented conservatively. |
| `match` | **Supported** | Supported branches are represented. |
| Ternary / null coalescing / short circuit | **Supported** | Supported expression flow is modeled conservatively. |
| `try` / `catch` / `finally` | **Supported** | Exception/control branches are represented. |
| Loops | **Supported** | Loop structure plus supported `break`/`continue` is represented. |
| Guard/continuation flow | **Supported** | Supported early continuation/termination patterns affect reachability. |
| Unreachable-code pruning | **Partial** | Conservative pruning only; PHPFlow is not a general theorem prover. |
| Runtime data-dependent branch truth | **Not supported** | PHPFlow does not execute predicates to discover runtime values. |

## Graph, viewer and impact contract

| Capability | Status | Notes |
| --- | --- | --- |
| Flow graph construction/traversal | **Supported** | Traversal and cycle detection are cycle-safe. |
| Route + orphan-message entry points | **Supported** | Impact roots include HTTP routes and applicable Messenger messages. |
| Mermaid export | **Supported** | Complete or route-scoped graph. |
| JSON graph export | **Supported** | Schema version **1.2**. |
| HTML export | **Supported** | Self-contained viewer with search, filters, minimap, lanes and path exploration. |
| Unified impact JSON | **Supported** | Schema version **1.0**. |
| Graph diff JSON | **Supported** | Schema version **1.0**. |
| Cross-schema graph diff | **Not supported** | `diff` rejects graph exports with different schema versions. |

Supported impact targets in `v0.1` are:

- database table, optionally filtered by operation;
- external HTTP endpoint;
- Messenger message;
- service;
- exception.

## Known conservative boundaries

The most important interpretation rule for PHPFlow output is:

> A missing edge means PHPFlow could not prove that relationship from the supported static
> patterns. It does not prove that the relationship can never happen at runtime.

Common reasons for an unresolved relationship include runtime-generated configuration,
reflection, dynamic receivers/method names, ambiguous interfaces, SQL/URLs assembled from
runtime-only values, and framework behavior that only becomes visible after booting the
application.

## Out of scope for v0.1

The following are intentionally not promised by the first release:

- executing or booting the analyzed application;
- emulating the Symfony dependency-injection container at runtime;
- connecting to databases, message brokers, HTTP services, cache servers, or mail systems;
- proving all PHP reachability/data-flow behavior;
- tracing production/runtime requests;
- inferring arbitrary side effects from unknown third-party libraries;
- treating absence from the graph as proof of runtime impossibility.

These boundaries keep `v0.1` deterministic, inspectable, and safe to run against a codebase.
