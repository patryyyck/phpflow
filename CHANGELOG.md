# Changelog

All notable changes to PHPFlow are documented in this file.

The project follows semantic versioning for tagged releases.

## [0.1.0] - 2026-08-29

First public release of PHPFlow.

### Added

- Static PHP project scanning based on `nikic/php-parser`.
- Symfony route discovery and recursive route inspection.
- Symfony Messenger dispatch, handler and routing analysis, including interface routing,
  union-typed handlers and cycle-safe recursive message flows.
- Service/interface resolution with supported Symfony service aliases and environment
  overrides.
- Recursive service and repository traversal, including same-class helper methods.
- Doctrine/DBAL effect detection for common `SELECT`, `INSERT`, `UPDATE` and `DELETE`
  patterns, including QueryBuilder support.
- External HTTP effect detection with static reconstruction of common URL/method patterns.
- Mail, filesystem, cache, exception, return-value and HTTP-response effects.
- Conservative control-flow modeling for conditions, `match`, guards, loops,
  `break`/`continue`, `try`/`catch`/`finally` and unreachable-code pruning.
- Flow graph construction, traversal, cycle detection and route-scoped subgraphs.
- Mermaid graph export.
- Versioned graph JSON export, schema `1.2`.
- Self-contained interactive HTML viewer with search, filters, presets, minimap, functional
  lanes, Messenger boundaries and path exploration.
- Impact analysis for database tables, external HTTP endpoints, Messenger messages,
  services and exceptions.
- Versioned impact JSON export, schema `1.0`.
- Graph comparison command with text and JSON diff output, schema `1.0`.
- Stable CLI surface with documented command names and exit-status conventions.
- v0.1 support matrix documenting Supported, Partial and Not supported analysis patterns.

### Release contract

PHPFlow `0.1.0` is a static analyzer. It does not execute the target application, boot the
Symfony kernel, connect to databases or brokers, or call external services. See
[`docs/SUPPORT.md`](docs/SUPPORT.md) for the complete analysis contract and
[`docs/CLI.md`](docs/CLI.md) for the public CLI contract.
