# Contributing to UFW

Thanks for considering a contribution. UFW is a compact framework with real production users, so the bar is **small, reviewable changes that preserve behaviour** - not opportunistic refactors.

This file complements [AGENTS.md](AGENTS.md) (which is written primarily for AI assistants and codifies the hard rules). Human contributors should read both.

## Before you start

- Read [docs/README.md](docs/README.md) to understand the intended mental model.
- For non-trivial work, open an issue or draft PR describing the problem first. Silent rewrites are hard to merge.
- If the change crosses public API (names, signatures, defaults), say so in the issue title - see "Ask first" in [AGENTS.md](AGENTS.md).

## Pull request style

- **Focused diffs.** One concern per PR. Reformatting, renames, and "while I'm here" cleanups belong in separate PRs.
- **Scoped commit messages.** Example: `forms(Bootstrap5): render invalid-feedback only on error`. The body should explain *why*, not restate the diff.
- **No unrelated file changes.** Do not commit vendor updates, IDE metadata, or version bumps with feature work.
- **Keep docs in sync.** If you change behaviour, update the matching guide under [docs/](docs/README.md). If you add an extension point, describe how to use it.
- **Respect PHP 7.4.** Runtime target is `php >=7.4` per [composer.json](composer.json). Do not introduce syntax or stdlib features that require newer PHP unless the change explicitly raises the minimum.

## Local checks

Before opening a PR:

```bash
# Lint every PHP file you touched
php -l path/to/file.php
```

If your environment has PHPStan / Psalm configured locally, run them - the repository does not ship a shared config yet (see [docs/framework-analysis.md](docs/framework-analysis.md) under "Testing and quality gates").

For behaviour changes, run a realistic end-to-end flow in an app that consumes UFW (e.g. the skeleton in [ufw-extras](https://github.com/ctebah/ufw-extras)). For form/view changes, verify checked / unchecked / focus / error states manually.

## Vendor policy

- Do **not** modify `vendor/`.
- Adding a new Composer dependency needs discussion - raise it in an issue first. The dependency surface is already non-trivial for a "micro" framework; each addition increases maintenance load for every adopter.

## Security

Do **not** open a public issue for a suspected vulnerability - see [SECURITY.md](SECURITY.md) for the coordinated disclosure process.

## License

By contributing you agree that your changes are released under the project license - [Zero-Clause BSD (0BSD)](LICENSE).
