# PHPFlow v0.1 CLI contract

The public CLI version for the first release is **PHPFlow 0.1.0**.

```bash
docker compose run --rm php php bin/phpflow --version
```

If PHP 8.4 and Composer dependencies are installed directly on the host, the equivalent
command is `php bin/phpflow --version`.

Symfony Console renders the version from `PhpFlow\Version::VERSION`. For `v0.1.0`, the public
command names below are considered stable.

| Command | Purpose |
| --- | --- |
| `scan` | Scan a PHP project. |
| `inspect` | Display the recursive flow for an HTTP route. |
| `export:mermaid` | Export the application flow graph as Mermaid. |
| `export:json` | Export the versioned PHPFlow graph JSON. |
| `export:html` | Export the self-contained interactive HTML viewer. |
| `impact` | Run unified impact analysis. |
| `impact:table` | Find entry points that can reach a database table. |
| `impact:http` | Find entry points that can reach an external HTTP endpoint. |
| `impact:message` | Find entry points that can dispatch a Messenger message. |
| `impact:service` | Find entry points that can reach an application class or method. |
| `impact:exception` | Find entry points that can throw an exception. |
| `diff` | Compare two PHPFlow graph JSON exports. |

## Exit status contract

PHPFlow follows Symfony Console command status conventions:

| Exit status | Meaning |
| ---: | --- |
| `0` | Command completed successfully. A valid analysis with no matching impact is still a successful execution. |
| `1` | Execution failed, for example because an input file/route cannot be read or resolved where required. |
| `2` | CLI input is invalid, for example an unsupported format, incompatible option combination, or invalid operation/depth. |

A graph `diff` that contains changes returns `0`: the presence of changes is analysis output,
not an execution failure.

## Output format contracts

The release keeps the existing versioned machine-readable formats unchanged:

| Output | Schema version |
| --- | --- |
| Graph JSON | `1.2` |
| Impact JSON | `1.0` |
| Graph diff JSON | `1.0` |

Graph exports with different schema versions cannot be compared by `diff`.

## Help

Use Symfony Console's normal help surfaces:

```bash
docker compose run --rm php php bin/phpflow list
docker compose run --rm php php bin/phpflow help impact
docker compose run --rm php php bin/phpflow export:json --help
```

Every PHPFlow command has a non-empty command description and typed argument/option help.
Invalid CLI option combinations are rejected before expensive project analysis where the
command can validate them independently of project contents.
