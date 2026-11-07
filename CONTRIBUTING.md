# Contributing to UFW

Thanks for considering a contribution. UFW is a compact framework with real production users, so the bar is **small, reviewable changes that preserve behaviour** - not opportunistic refactors.

This file complements [AGENTS.md](AGENTS.md) (which is written primarily for AI assistants and codifies the hard rules). Human contributors should read both.

## Before you start

- Read [docs/README.md](docs/README.md) to understand the intended mental model.
- For non-trivial work, open an issue or draft PR describing the problem first. Silent rewrites are hard to merge.
- If the change crosses public API (names, signatures, defaults), say so in the issue title - see "Ask first" in [AGENTS.md](AGENTS.md).

## Pull request style

- **English only** for docs, comments, CLI output, and test fixtures (unless a test explicitly exercises i18n/Unicode data).
- **Focused diffs.** One concern per PR. Reformatting, renames, and "while I'm here" cleanups belong in separate PRs.
- **Scoped commit messages.** Example: `forms(Bootstrap5): render invalid-feedback only on error`. The body should explain *why*, not restate the diff.
- **No unrelated file changes.** Do not commit vendor updates, IDE metadata, or version bumps with feature work.
- **Keep docs in sync.** If you change behaviour, update the matching guide under [docs/](docs/README.md). If you add an extension point, describe how to use it.
- **Respect PHP 7.4.** Runtime target is `php >=7.4` per [composer.json](composer.json). Do not introduce syntax or stdlib features that require newer PHP unless the change explicitly raises the minimum.

## Local checks

Before opening a PR:

```bash
composer install

# Static analysis (PHP 8.2+, same as CI)
composer phpstan

# Unit tests on the host (no MySQL; component suite skips if DB is down)
composer test
composer test-component

# Full suite + Clover coverage (Docker: MySQL 8 + PHP 8.2 + PCOV)
composer test-all
composer coverage-check
composer coverage-gaps   # optional: lists uncovered lines in coverage/gaps.txt
```

Lint every PHP file you touch with the same major PHP your app targets (library code stays **PHP 7.4**-compatible):

```bash
php -l path/to/file.php
```

CI on GitHub runs **`composer phpstan`** and **`docker/phpunit.sh`** with **`coverage-check`**, then uploads **`coverage/clover.xml`** to [Codecov](https://codecov.io/gh/ctebah/ufw) (free for this public repo; see [`.github/workflows/ci.yml`](.github/workflows/ci.yml)). Maintainers: add GitHub repo secret **`CODECOV_TOKEN`** — either the **repository** token (Codecov → open **`ctebah/ufw`** → **Configuration** / setup wizard) or your account **Settings → Global upload token** (same secret name on GitHub). Details: [docs/testing.md](docs/testing.md).

For behaviour changes, run a realistic end-to-end flow in an app that consumes UFW (e.g. the skeleton in [ufw-extras](https://github.com/ctebah/ufw-extras)). For form/view changes, verify checked / unchecked / focus / error states manually.

## Vendor policy

- Do **not** modify `vendor/`.
- Adding a new Composer dependency needs discussion - raise it in an issue first. The dependency surface is already non-trivial for a "micro" framework; each addition increases maintenance load for every adopter.

## Agent guides (`CLAUDE.md`)

Policy for humans and agents lives in **[AGENTS.md](AGENTS.md)** only. **`CLAUDE.md`** is a **Git symlink** to that file (mode `120000` in the index) so Linux CI and Unix checkouts get a real link.

**Windows:** turn on **Developer Mode** (Settings → System → For developers), then ensure this clone has `git config core.symlinks true` (already set locally in many setups). Without that, Git cannot create the symlink on checkout and native tools may not read a WSL-only link on `C:`. After enabling, recreate if needed: `mklink CLAUDE.md AGENTS.md` from an elevated or Developer-Mode shell, or `ln -sf AGENTS.md CLAUDE.md` in Git Bash/WSL inside the repo.

## Security

Do **not** open a public issue for a suspected vulnerability - see [SECURITY.md](SECURITY.md) for the coordinated disclosure process.

## License

By contributing you agree that your changes are released under the project license - [Zero-Clause BSD (0BSD)](LICENSE).
