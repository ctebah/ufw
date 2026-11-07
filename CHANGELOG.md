# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0-rc.1] - 2026-11-07

**Release candidate** for 1.1.0 — same API intended for GA unless blockers are found. Composer: `1.1.0-rc.1` or `^1.1@RC`.

**Upgrade from 1.0:** see [Migration: 1.0 → 1.1](docs/migration-1.0-to-1.1.md) (opt-in features and upgrade notes).

### Added

- **Records:** opt-in `ChangedOnly` and `OptimisticLock` behaviours. They stay off unless a record declares them. `ChangedOnly` writes only fields that differ from the snapshot taken when the row was read; a changed primary key is written, and `WHERE` still uses the key from that snapshot. `OptimisticLock` matches a `version` column and raises `StaleRecord` when the row changed since it was read. After a successful update the row is read back, unless the record already declares `ReadAfterSave`.
- **Db:** `config/dbconns.php` may set `___opts` `throwOnError`, `logFile`, and `logPost`. Failures are still logged; with `throwOnError` the same log line is followed by `QueryException`. `config/app.php` key `db.class` is what `getDb()` returns. `\Ufw\Db\Db::instance()` still builds the framework class.
- **Tests (new in 1.1):** PHPUnit 11 on PHP 8.2 — unit and component suites in `phpunit.xml.dist`, Docker/MySQL harness, Clover coverage. Composer scripts: `test`, `test-component`, `test-all`. The **library** still targets PHP 7.4; consuming apps keep their own PHP version.
- **Static analysis (new in 1.1):** PHPStan 2 **level 10** on all of `src/` (`phpstan.neon.dist`, no baseline). Run with `composer phpstan` (PHP 8.2 dev dependency). Optional Solarium stubs in `scanFiles` for `Model\Record\Solr`.

### Fixed

- **Db:** `query()` returns `true` after successful INSERT/UPDATE/DELETE (1.0.x often returned a falsy value for those statements). Failure checks (`if (!$db->query(...))`) behave the same.
- **Forms:** multi-select controls validate against **unfiltered** POST values; unknown checkbox/select values no longer pass `InArray` after `setValue` stripped them on 1.0.x.

## [1.0.2] - 2026-09-29

### Fixed

- **Forms (reCAPTCHA):** on GET forms, disable Google’s injected `g-recaptcha-response` field so only the app’s captcha input is submitted—avoids duplicating the token in the query string (~2× URL length). Bootstrap 3/4/5 partials.

## [1.0.1] - 2026-06-05

### Fixed

- **JsOut:** remove semicolon-collapse regex from `_sanitize()` that could corrupt `jsInline` payloads (e.g. `json_encode` data with `;;` inside string literals), causing JShrink to throw during minify.

## [1.0.0]

### Added

- Initial public release of UFW.
