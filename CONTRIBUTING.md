# Contributing to PHPFlow

Thanks for taking the time to improve PHPFlow.

PHPFlow is a static analyzer: it reconstructs flows from source code and supported framework
configuration without booting or executing the target application. Contributions should preserve
that contract.

## Before opening a pull request

For bugs or unsupported static patterns, an issue with a small reproducible example is usually
the best starting point. A minimal fixture is much easier to reason about than a full application.

Useful reports include:

- the PHPFlow command you ran;
- the expected relationship or effect;
- the actual result;
- a minimal PHP/configuration snippet that reproduces the behavior;
- PHP, Symfony, Doctrine or Messenger versions when relevant.

Please remove credentials, tokens, internal hostnames, customer data and proprietary source code
from examples.

## Development setup

The repository workflow uses Docker, Docker Compose and GNU Make. PHP does not need to be
installed on the host.

```bash
make setup
make test
```

Release-contract checks can be run with:

```bash
make release-check
docker compose run --rm php composer validate --strict
```

## Project structure

The main code is organized by responsibility:

- `src/Domain` — graph and analysis domain objects;
- `src/Application` — application services and use cases;
- `src/Infrastructure` — scanning and framework/configuration integration;
- `src/Ast` — PHP AST analysis and static reconstruction;
- `src/Console` — CLI commands and renderers;
- `src/Exporter` — JSON, Mermaid and HTML exports;
- `tests` — unit/integration tests and synthetic fixtures.

The support contract for v0.1 is documented in [`docs/SUPPORT.md`](docs/SUPPORT.md). Stable CLI
names and machine-readable schemas are documented in [`docs/CLI.md`](docs/CLI.md).

## Adding support for a new static pattern

Prefer the smallest change that proves the relationship safely.

A typical contribution should:

1. add or extend a synthetic fixture under `tests/Fixtures`;
2. add a regression test demonstrating the currently missing behavior;
3. implement the AST/configuration change;
4. keep unsupported/dynamic cases conservative rather than guessing;
5. run the full test suite.

When PHPFlow cannot prove a relationship statically, omitting the edge is preferable to inventing
one.

## Tests

Run:

```bash
make test
```

Before proposing release-sensitive changes, also run:

```bash
make release-check
docker compose run --rm php composer validate --strict
```

New behavior should normally include a regression test. Changes to public JSON output must preserve
the documented schema contract or intentionally introduce and document a new schema version.

## Pull requests

Keep pull requests focused. Please explain:

- the problem being solved;
- the static pattern being recognized or behavior being changed;
- how it was tested;
- whether public CLI or JSON contracts are affected.

Avoid unrelated formatting or refactoring in the same pull request when possible.

By contributing, you agree that your contribution will be licensed under the project's
[MIT License](LICENSE).
