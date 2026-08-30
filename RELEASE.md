# Release checklist

This checklist is for publishing PHPFlow `v0.1.0`.

## Before tagging

- [ ] Start from a clean `main` branch containing the validated release commits.
- [ ] Run `make test` and verify the full PHPUnit suite passes.
- [ ] From a clean clone, run `make setup` followed by `make demo` and open `/tmp/phpflow-demo.html`.
- [ ] Run `make release-check` and verify the deterministic release contract checks pass.
- [ ] Run the representative real-project smoke tests used during development:
  - [ ] `scan`
  - [ ] `inspect`
  - [ ] `export:json`
  - [ ] `export:html`
  - [ ] `impact`
  - [ ] `diff`
- [ ] Open a generated HTML graph from a representative large project and verify search,
      minimap, lanes and path highlighting.
- [ ] Verify `docker compose run --rm php php bin/phpflow --version` reports `PHPFlow 0.1.0`.
- [ ] Verify `php bin/phpflow list` exposes the documented v0.1 commands.
- [ ] Verify graph JSON reports schema `1.2`.
- [ ] Verify impact JSON reports schema `1.0`.
- [ ] Verify graph diff JSON reports schema `1.0`.
- [ ] Review [`docs/SUPPORT.md`](docs/SUPPORT.md) for accidental claims beyond the tested
      static-analysis surface.
- [ ] Review [`CHANGELOG.md`](CHANGELOG.md).
- [ ] Verify there are no private fixtures, credentials, tokens, local paths or placeholder
      repository-owner values in tracked release files.
- [ ] Run `composer validate --strict` in the development container.

## Tagging

After all checks above pass:

```bash
git checkout main
git pull --ff-only
git tag -a v0.1.0 -m "PHPFlow v0.1.0"
git push origin v0.1.0
```

Do not change `PhpFlow\Version::VERSION` after the final validation commit and before tagging.

## GitHub release

Create a GitHub release from tag `v0.1.0`.

Suggested title:

```text
PHPFlow v0.1.0
```

Use the `0.1.0` section of [`CHANGELOG.md`](CHANGELOG.md) as the basis of the release notes.
Highlight that PHPFlow is a static analyzer and link to the support matrix so users can see
the exact v0.1 boundaries.

## After publishing

- [ ] Confirm the tag and GitHub release are visible publicly.
- [ ] Confirm installation from a fresh clone works.
- [ ] Re-run `docker compose run --rm php php bin/phpflow --version` from that fresh clone.
- [ ] Create the next development version only in a subsequent commit.
