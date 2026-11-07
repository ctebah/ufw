# AGENTS Guide for UFW

This file guides AI agents and human contributors working in this repository.

## Purpose

UFW welcomes AI-assisted development. Treat the agent as a careful pair engineer: prefer small, reviewable changes that preserve framework behavior and compatibility.

## Scope and precedence

- This guide applies to the whole `ufw` repository.
- If a deeper folder adds its own `AGENTS.md`, that local file overrides this one for that subtree.

## Read this first

Before implementing anything non-trivial, read:

- `README.md`
- `docs/README.md`
- `docs/controllers.md`
- `docs/middleware.md`
- `docs/forms-and-validation.md`
- `docs/views-layouts-partials.md`

## Hard compatibility rules

- Runtime target is `php >=7.4` (see `composer.json`): do not introduce syntax/features that require newer PHP.
- Avoid backward-incompatible behavior changes in `Ufw\\*` unless explicitly requested.
- Prefer opt-in options over changing existing defaults.
- Keep public method names/signatures stable unless the task explicitly requires API changes.

## Editing rules

- Keep diffs focused and minimal; do not do opportunistic refactors.
- **English only** for documentation, comments, CLI/script output, and test fixtures (except data that deliberately tests i18n or Unicode).
- Do not modify `vendor/`.
- Preserve existing naming and folder conventions.
- If touching framework partials/helpers, check app-level override/fallback behavior.

## Forms and view partials policy

- Respect form helper variants and their rendering assumptions.
- Bootstrap 3/4 form partials currently use Material Icons markers in some controls.
  - If changed, document dependencies and migration impact.
- Bootstrap 5 partials should not require external icon fonts by default.
- If adding a new rendering variant, add it as a separate helper path (do not silently mutate legacy output).

## Safety checks before finishing

- Run `php -l` on every modified PHP file.
- For behavior changes, verify at least one realistic flow end-to-end.
- For UI/form changes, verify checked/unchecked/focus/error states.
- Update docs when behavior, extension points, or dependencies change.

## Commit guidance

- Use scoped commit messages (e.g. `forms(Bootstrap5): ...`).
- Commit message body should explain why, not only what.
- Do not combine unrelated changes in one commit.

## Ask first in these cases

Stop and ask before proceeding if a task implies:

- public API breaks,
- widespread rename/move,
- new dependency introduction,
- changing established defaults used by existing apps,
- destructive data/file operations.

## Other agent entry points

- **`CLAUDE.md`** — symlink to this file for Claude Code; **canonical text is only `AGENTS.md`**.
- Edit **`AGENTS.md`** only. Recreate the link after clone if missing: `ln -sf AGENTS.md CLAUDE.md` (Unix/WSL) or enable Windows **Developer Mode** and `git config core.symlinks true` so Git checks out the symlink.
