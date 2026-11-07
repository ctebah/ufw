# Test fixture applications

Minimal UFW app trees used by PHPUnit (`config/`, optional `views/`, `dbconns.php`, etc.).

| Directory | Role |
|-----------|------|
| `fixture-app` | Default bootstrap app; component DB host/port; forms and view partial coverage |
| `fixture-throw-app` | `throwOnError` enabled in db config |
| `fixture-silent-app` | `throwOnError` off / silent query errors |
| `fixture-log-app` | Custom DB error log path |
| `fixture-bad-app` | Invalid / edge bootstrap config |

PHP tests should resolve paths via **`Ufw\Tests\Support\TestAppPaths`** (`app()`, `throwApp()`, …), not hard-coded `tests/fixture-*` paths.
