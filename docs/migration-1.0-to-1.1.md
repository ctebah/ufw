# Migration: UFW 1.0 → 1.1

← [Documentation index](README.md)

For applications on **UFW 1.0** (`v1.0.x`) moving to **1.1** (`^1.1` or your pinned tag).

**Composer:** bump `ctebah/ufw` to `^1.1`, run `composer update ctebah/ufw`, then smoke-test forms, menus, list screens, and record saves in your app.

---

## New features (opt-in)

These add behaviour **only when you turn them on**. Existing record classes and config stay as in 1.0 until you adopt them.

### Database: `throwOnError`

In `config/dbconns.php`, optional top-level **`___opts`**:

```php
'___opts' => [
    'throwOnError' => true,
],
```

When `true`, failed SQL is still logged as before, then **`Ufw\Db\QueryException`** is thrown. When omitted or `false`, failed queries return `false` (same as 1.0).

See [database.md](database.md) and [config-reference.md](config-reference.md) for `logFile` and `logPost`.

### Database: `db.class`

In `config/app.php`:

```php
'db.class' => \Your\App\Db\Db::class,
```

**`getDb()`** on `Ufw\Model\Base`, `Ufw\Model\Record\Base`, and `Ufw\Controller\Base` returns an instance of that subclass (must extend `\Ufw\Db\Db`). **`Db::instance()`** on the framework class still constructs `\Ufw\Db\Db`.

Apps that override **`getDb()`** themselves are unaffected until they switch to this config hook.

### Records: `ChangedOnly` behaviour

Opt-in per record class in **`behaviours()`**. On update, only columns that differ from the read snapshot are written; see [record-behaviours-solr.md](record-behaviours-solr.md).

### Records: `OptimisticLock` behaviour

Opt-in per record class. Uses a **`version`** column (configurable); concurrent updates can raise **`Ufw\Model\Exception\StaleRecord`**. See [record-behaviours-solr.md](record-behaviours-solr.md).

---

## Changes when you upgrade

### Fixes and form validation

| Area | Change |
|------|--------|
| **JsOut** (1.0.1) | Safer inline JS sanitization (no `;;` collapse inside string literals). |
| **reCAPTCHA forms** (1.0.2) | On GET, disable Google’s extra `g-recaptcha-response` field in Bootstrap 3/4/5 partials. |
| **Multi-select forms** | `InArray` runs on **unfiltered** submitted values; options not in `multiOptions` fail validation (1.0 could accept tampered POST after `setValue` stripped unknown keys). |

### Config and routes: string keys in some normalizers

When loading **`config/app.php`**, route definitions, and **ApiClient** populate maps, associative arrays are reduced to **string keys** only. Numeric keys in those config arrays are dropped. Typical PHP config files use string keys; avoid numeric keys in route/config arrays.

### Record hydration: `normalizeAssocRow`

MySQL rows loaded into **`Record\Base`** subclasses keep only **string** column keys in the internal array. mysqli associative fetch already uses string keys; this rarely affects apps.

### `Db::query()` return value

Successful **INSERT/UPDATE/DELETE** statements return **`true`** instead of a falsy value. Code that only checks failure (`if (!$db->query(...))`) behaves the same.

### `filter_string_keyed_array`

Used for **HTML attribute** maps (menu wrappers, JS asset `attribs`), not for request bodies. Non-string attribute keys are omitted.

---

## Suggested upgrade order

1. Upgrade **UFW** in a branch on the target **1.1** tag or constraint.
2. Smoke your app: login menu, one admin form, one list backed by `InfoHash`, one record save with relations, one search/filter form.
3. Enable **`throwOnError`**, **`ChangedOnly`**, or **`OptimisticLock`** in new code or after explicit review when you want those features.

In the framework repo, **`composer test-all`** and **`composer phpstan`** should be green on the release you ship.

---

**See also:** [CHANGELOG](../CHANGELOG.md) · [Testing](testing.md) · [Database connections](database.md) · [Models and records](models-and-records.md)
