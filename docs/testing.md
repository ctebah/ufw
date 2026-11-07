# Testing

← [Documentation index](README.md)

Library code stays compatible with **PHP 7.4**. The test run is **PHP 8.2** and **PHPUnit 11**.

Two suites:

- **Unit** (`tests/Unit`) — no database. Files are grouped by **domain** under subfolders (`Application/`, `ApiClient/`, `Form/`, `Db/`, …). Older or cross-cutting tests may still sit at `tests/Unit/*.php` or under `Misc/` / `Integration/`.
- **Component** (`tests/Component`) — one record, real MySQL, `save()`, read the row back. Covers every bundled behaviour (`InsertId`, `DefaultFieldValues`, `ReadAfterSave`, `Json`, `ChangedOnly`, `OptimisticLock`) plus query errors, including `___opts['throwOnError']` in `dbconns.php`. Skipped when MySQL is not reachable.

**Test class naming:** name classes after the **component or behaviour** under test (`ControllerBaseTest`, `ApiClientHelperTest`, `FormPopulateTest`). Match the PHP class name to the file name (`FormPopulateTest.php`). Prefer extending an existing domain file; put reusable probes in `tests/Support/` (`TestHttpRequest`, `ApiClientTestApi`, form fixtures under `tests/Support/Fixtures/`).

**Unit layout (domain folders):**

| Folder | Typical scope |
|--------|----------------|
| `Application/` | bootstrap, dispatch, router, run loop |
| `ApiClient/` | HTTP client, helper, actionable flows |
| `Controller/`, `Request/`, `Response/`, `Route/`, `Middleware/` | HTTP stack |
| `Form/` | elements, validators, CSRF, render |
| `Db/` | query layer, `Db::forApp`, critical paths |
| `Model/` | `Record`, behaviours |
| `InfoHash/`, `Helper/`, `JsOut/`, `Views/` | supporting types and partials |
| `Infra/`, `Integration/`, `Misc/` | paths/registry, smoke, multi-domain gaps |

**Fixture apps:** minimal app trees live under **`tests/test-apps/`** (`fixture-app`, `fixture-throw-app`, …). Use **`Ufw\Tests\Support\TestAppPaths`** in PHP tests instead of string paths (see `tests/test-apps/README.md`).

## Platforms (Linux, macOS, Windows)

The suite is **not Windows-specific**. PHPUnit runs the same on any OS with PHP 8.2.

| How you run | Windows | Linux / macOS |
|-------------|---------|----------------|
| Unit only | `composer test` | same |
| Component (host MySQL on `:13306`) | `composer test-component` | same |
| Full suite + coverage in Docker | `composer test-all` → `bin/docker-phpunit.php` → **`docker/phpunit.ps1`** | `composer test-all` → **`docker/phpunit.sh`** |

Temp files in tests use **`sys_get_temp_dir()`** and **`DIRECTORY_SEPARATOR`** (see `tests/Support/TempFiles.php`). Some tests pass **Windows-style config paths** (`C:\…`, `\\server\share\…`) into `Paths::exceptionLogFilePath()` to assert that **absolute-path detection** works; they do not require running on Windows.

**Requirements:** Docker for `test-all`; optional local MySQL for component tests on the host. No `PHP_OS` skips in the tree.

## Composer scripts

| Script | What it runs |
|--------|----------------|
| `composer test` | Unit suite only (no Docker) |
| `composer test-component` | Component suite against `127.0.0.1:13306` (skips if MySQL is down) |
| `composer test-all` | Full suite in Docker with PCOV (HTML under `coverage/html/`, text summary, Clover) |
| `composer test-all-html` | Alias for `test-all` (same HTML + Clover output) |
| `composer phpstan` | PHPStan level 10 on all of `src/` |

Configuration: **`phpunit.xml.dist`** (single config for the whole tree).

Before a **1.1** release tag, **`composer test-all`**, **`composer coverage-check`**, and **`composer phpstan`** should be green.

### Coverage target

PHPUnit measures **lines and methods** on all of **`src/`**, including bundled **`src/views/`** partials (exercised via `Partial` / form render helpers in tests such as `Views/PartialRenderingTest`). Team goal: **100%** lines and methods on that scope; **`composer coverage-check`** enforces a **98%** floor (release red line).

After a full run:

```powershell
composer test-all
composer coverage-check
composer coverage-gaps
```

`coverage-check` reads `coverage/clover.xml` (line hits in Clover). **`coverage/gaps.txt`** lists all uncovered methods and lines in one file (lines as ranges, e.g. `90-91, 109-119`). Per-class totals: **`coverage/summary.txt`**. Artifacts under **`coverage/`** (gitignored), including browsable **`coverage/html/`** after `composer test-all`.

**CI:** the same Clover file is uploaded to [Codecov](https://codecov.io/gh/ctebah/ufw) (OSS/free). The README badge shows the latest project coverage; **`codecov.yml`** mirrors the **98%** project target. Local runs do not upload unless you use the Codecov CLI yourself.

## Docker (full suite + coverage)

`docker-compose.yml` starts MySQL 8 (`ufw_test` on host port **13306**) and a PHP 8.2 CLI image with `mysqli` and PCOV. PHPUnit runs **inside** the PHP container via `docker compose run --rm php` (a fresh PHP container per run). **`docker/phpunit.ps1`** / **`docker/phpunit.sh`** run **`docker compose down`** when the run finishes (exit code preserved), so the stack does not stay up unless you opt out.

To keep MySQL running across several PHPUnit invocations (faster iteration), set **`UFW_DOCKER_KEEP=1`** for that shell and start MySQL once:

```powershell
$env:UFW_DOCKER_KEEP = '1'
./docker/test-stack.ps1 up
./docker/phpunit.ps1 vendor/bin/phpunit -c phpunit.xml.dist
# when done: ./docker/test-stack.ps1 down
```

```powershell
composer test-all
# or:
./docker/phpunit.ps1
./docker/phpunit.bat
./docker/phpunit.sh
```

Unit only in Docker:

```powershell
./docker/phpunit.ps1 vendor/bin/phpunit --testsuite unit
```

Line coverage (PCOV, not Xdebug) follows `phpunit.xml.dist` `<source><include>`: all of **`src/`** (PHP classes and **`src/views/`** scripts). Default `composer test-all` writes **`coverage/summary.txt`** and **`coverage/clover.xml`** (gitignored). One-off text-only report:

```powershell
./docker/phpunit.ps1 vendor/bin/phpunit --coverage-text
```

Inside the compose network the database host is `mysql` on port `3306` (`UFW_TEST_DB_*` in the compose file). Port **13306** on the host is only for optional manual debugging against the same database.

## On the host (unit + PHPStan)

PHP 8.2 and Composer, then:

```bash
composer install
composer test
composer test-component
composer phpstan
```

Use **`composer test-all`** when you need MySQL component tests and coverage without managing containers yourself.

`php -l` for framework sources in consuming apps still uses the PHP version your application targets (UFW library code remains PHP 7.4-compatible).

### PHPUnit warnings and skips

On a clean tree, **`composer test-all`** (Docker, PHP 8.2) should report **no PHP warnings** in the summary. To list any message:

```powershell
vendor/bin/phpunit --testsuite unit --display-warnings --display-deprecations
```

**Skips:** `UploadedTmpFileTest::testMoveToRejectsUnwritableTargetDirectory` is skipped on **Windows** (read-only directory fixture is unreliable there); it runs on Linux/Docker.

**Coverage gaps by design:** production **`die()`** in `Application::terminateAfterEmit()` and `mydie()` when suppress constants are not set. Tests define **`UFW_APPLICATION_SUPPRESS_EXIT`** and **`UFW_MYDIE_SUPPRESS_EXIT`** so behaviour is exercised without exiting; Clover still lists those lines — see **`composer coverage-gaps`**.

After `composer test-all`, open **`coverage/html/index.html`** for the line-level report.

## PHPStan

PHPStan 2 runs on PHP 8.2 and analyses all of **`src/`** as PHP 7.4 (`phpstan.neon.dist`, **level 10**, no baseline). It does not need MySQL. `composer phpstan` should report no errors on a clean tree. Concrete `{@see \Ufw\Model\Record\Base}` subclasses can declare `@property` for row columns when you want stricter typing on magic row access.

```bash
composer phpstan
```
