# Config reference

← [Documentation index](README.md)

A lookup table of every key the framework reads from your `config/app.php`, your `config/dbconns.php`, the PHP `Registry`, and PHP constants. Each row links to the guide that explains behaviour in context - this page is a map, not a tutorial.

Two top-level containers:

- **`config/app.php`** - loaded inside `Application::__construct()` via `Paths::application() . '/config/app.php'` unless you call `setConfig()` earlier. Reached at runtime through `$application->getConfig()`.
- **`config/dbconns.php`** - loaded lazily by `Db` on first use.

## `config/app.php`

### `dispatch.*` (routing and controller resolution)

| Key | Read by | Purpose |
|-----|---------|---------|
| `dispatch.routes` | `Router` | Ordered route definitions (route name → definition array). See [routing.md](routing.md) and [routing-deep-dive.md](routing-deep-dive.md). |
| `dispatch.controllers` | `Application::setDispatchControllers()` | List of logical controller names accepted by `checkIfControllerIsRegistered`. Keys of `controller_aliases` are merged in. |
| `dispatch.defaults` | `Application::setDispatchDefaults()` | Default `controller` / `action` / `module` when a route omits them. |
| `dispatch.controller_aliases` | `Route\Base::getControllerClass()`, `Application::hasAction()` | URL-facing id → canonical id map (e.g. `blog` → `news`). See [routing-deep-dive.md](routing-deep-dive.md). |
| `dispatch.module.{name}.controllers` | `Application::getDispatchControllers($module)` | Per-module equivalent of `dispatch.controllers`. |
| `dispatch.module.{name}.defaults` | `Application::getDispatchDefaults($module)` | Per-module defaults. |
| `dispatch.module.{name}.controller_aliases` | Per-module alias map | Same shape as top-level `controller_aliases`. |

### `form.*` (server-rendered forms)

| Key | Read by | Purpose |
|-----|---------|---------|
| `form.helper` | `Form\Base::getRenderHelperName()`, `Form\Element\Base::getRenderHelperName()`, `Helper\Menu` | Name of the form render helper (e.g. `bootstrap3`, `bootstrap4`, `bootstrap4plain`, `bootstrap5`). Drives `src/views/partials/{helper}/` lookups. See [forms-and-validation.md](forms-and-validation.md) and [helpers.md](helpers.md). |

Element-level option that reads config indirectly:

| Option (on `Form\Element\Csrf`) | Purpose |
|---------------------------------|---------|
| `csrfComponent` | Class-string of a `Csrf`-compatible component (must provide static `token()` / `validate()`). Defaults to `Ufw\Component\Csrf`. See [csrf.md](csrf.md). |

### `assets.*` and `jsOutStorage.*`

| Key | Read by | Purpose |
|-----|---------|---------|
| `assets.serve-minified` | `Helper\Assets::url()` | Truthy → emit `.min.*` URLs for CSS/JS bundles. |
| `css` (per-bundle payload) | `Helper\Assets` → `Ufw\Utils\Asset\Css` | CSS manifest format is application-defined; see [assets-and-jsout.md](assets-and-jsout.md). |
| `js` (per-bundle payload) | `Helper\Assets` → `Ufw\Utils\Asset\Js` | JS manifest, same idea. |
| `jsOutStorage.webpath` | `JsOut` | Web path prefix for hashed combined bundle URLs. |
| `jsOutStorage.absrootpath` | `JsOut` | Filesystem root that joins `webpath`. Both required for file output. |
| `jsOutStorage.serve-minified` | `JsOut` | Truthy → emitted `src` uses `.min.js`. |
| `jsOutStorage.readyWrapper` | `JsOut` | `module` (default), `classic`, `jquery`, or `none`. `dom` is treated as `classic`. See [assets-and-jsout.md](assets-and-jsout.md). |

### `storage`

| Key | Read by | Purpose |
|-----|---------|---------|
| `storage` | `Form\Element\File` | Filesystem path(s) for uploaded file handling. App-defined shape. |

### `logging.*`

| Key | Read by | Purpose |
|-----|---------|---------|
| `logging.exception_log_path` | `Paths::exceptionLogFilePath()` (via `Application::getExceptionLogFilePath()` → `logExceptionToFile()`) | Where `Application::logExceptionToFile()` appends via `error_log(..., 3, $path)`. Omitted → `Paths::application() . '/log/exceptions.txt'`. `false` or `''` (after trim) → file logging disabled. Absolute path (`/…`, Windows `C:\…` or `\\server\…`) → used as-is. Any other non-empty string → relative to `Paths::application()`. See [error-handling.md](error-handling.md). |

### Runtime environment

| Key | Read by | Purpose |
|-----|---------|---------|
| `env` | `Envs::runtimeLabel()` | Runtime label (e.g. `development`, `production`). See [flavors-multi-app.md](flavors-multi-app.md) and `Helper\Menu` `env` item filter. |
| `flavor` | Your app | Not read by UFW core; common application-level key for multi-tenant / multi-product setups. See [flavors-multi-app.md](flavors-multi-app.md). |
| `db.class` | `Db::forApp()` via `getDb()` | Subclass of `\Ufw\Db\Db` returned by record, model, and controller `getDb()`. Absent, or config not loaded yet, means `\Ufw\Db\Db`. Does not change `\Ufw\Db\Db::instance()`. See [database.md](database.md). |

## `config/dbconns.php`

Returns an associative array keyed by **`conn_ident`** string. Each entry is consumed by `Ufw\Db\Db::connect()`.

One optional top-level key is not a connection: **`___opts`**, an array of Db options. The first `Db` constructed in the process reads it and then drops the key so it is never a `conn_ident`. Options: **`throwOnError`** (absent means silent query errors), **`logFile`** (absent means `application/log/error_dblog.tmp`; `false` or `''` disables the file), **`logPost`** (absent means the log includes `$_POST`). See [database.md](database.md).

| Entry key | Required | Purpose |
|-----------|----------|---------|
| `host` | Yes | MySQL host or IP. |
| `user` | Yes | MySQL username. |
| `password` | Yes | MySQL password. |
| `dbname` | No | Default database for this connection (passed as 4th arg to `mysqli_connect`). |
| `port` | No | TCP port; defaults to `3306` when empty or missing. |
| `socket` | No | Unix socket path; passed as 6th arg to `mysqli_connect`. |
| `collation` | No | Connection collation applied on connect. |
| `time_zone` | No | Connection time zone applied on connect. |

See [database.md](database.md) for the connection selection model (per-call `conn_ident`, per-record `$dbconnIdent`).

## PHP constants

Resolved by the framework without going through `getConfig()`:

| Constant | Read by | Purpose |
|----------|---------|---------|
| `APPLICATION_PATH` | `Paths::application()` | Filesystem root of the application. Fallback after `Paths::setApplicationPath()` and Registry `application_path`. |
| `APPLICATION_ENV` | `Envs::runtimeLabel()` | Runtime label fallback after config `env` and Registry `env`. |
| `DBLOG_ENV` | `Db::__construct()` | Truthy in a **dev-only** bootstrap enables shutdown-time SQL log dump. Never set in production. |

## `Registry` keys used by the framework

`Ufw\Registry::getInstance()` is a process-wide key–value store. Keys the framework reads or writes:

| Key | Read/Write | Purpose |
|-----|-----------|---------|
| `application` | Written by `Application::__construct()`; read by records, helpers, routes, `Envs`, and `BreakExecution`. | Canonical reference to the `Ufw\Application` instance. |
| `application_path` | Read by `Paths::application()` when set before `Application` construction. | Filesystem root override (Registry-style alternative to the `APPLICATION_PATH` constant). |
| `env` | Read by `Envs::runtimeLabel()` after config `env`. | Runtime label override. |
| `translator` | Read by `Helper\Translate`. | Optional `callable(string $message): string` registered at bootstrap for i18n. See [helpers.md](helpers.md). |

Your app may set additional keys (e.g. `logger`, `user`, `flavor`) - those are application conventions, not framework-defined.

## Minimal config

The smallest `config/app.php` that dispatches a single route (from [getting-started.md](getting-started.md)):

```php
return [
    'dispatch' => [
        'controllers' => ['index'],
        'defaults' => [
            'controller' => 'index',
            'action' => 'index',
        ],
        'routes' => [
            'home' => [
                'type' => '\Ufw\Route\Literal',
                'path' => '/',
                'defaults' => ['controller' => 'index', 'action' => 'index'],
            ],
        ],
    ],
    'form' => ['helper' => 'bootstrap5'],
];
```

---

**Next:** [Documentation index](README.md) - jump back to the guide list.
