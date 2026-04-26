# Error handling

← [Documentation index](README.md)

How UFW turns exceptions into HTTP responses, where they get logged, and what you should change before production. The main pieces are `Ufw\Exception\BreakExecution` (deliberate control flow), `Application::run()` (top-level catch), `Application::dispatchError()` (error page rendering), and `Application::logExceptionToFile()` (file-based logging).

## Two exception paths

`Application::run()` wraps dispatch in a two-arm `try`:

```text
try {
    $this->dispatch($request);
} catch (\Ufw\Exception\BreakExecution $e) {
    // deliberate control flow - honor status code, maybe run error action
} catch (\Exception $e) {
    // anything else - log, reset response to 404, run error action
}
```

See [`src/Application.php`](../src/Application.php) for the implementation.

### Path 1: `BreakExecution`

`Ufw\Exception\BreakExecution` (see [`src/Exception/BreakExecution.php`](../src/Exception/BreakExecution.php)) is the documented **control-flow** mechanism. Throwing it sets the response status (when non-zero) and unwinds the stack to `Application::run()`.

```php
throw new \Ufw\Exception\BreakExecution('Not found', 404);
throw new \Ufw\Exception\BreakExecution('Unauthorized', 401);
throw new \Ufw\Exception\BreakExecution('Forbidden', 403);
throw new \Ufw\Exception\BreakExecution('Moved', 302);
```

Handling in `run()`:

| Status range | Behaviour |
|--------------|-----------|
| 300–310 (redirects) | Response is emitted as-is, script dies. No error action. |
| 401 with `WWW-Authenticate` header already set | Response is emitted as-is (HTTP Basic / Bearer challenges work). |
| 400 / 401 / 403 / 404 | Mapped to error actions `bad-request`, `unauthorized`, `forbidden`, `not-found`; exception is logged; response is reset; `dispatchError()` runs. |
| Anything else | Logged; response reset; `dispatchError('not-found')` runs (same fallback as a plain 404). |

Use `BreakExecution` where UFW already expects it (auth gates in middleware, 404 inside router, redirects in helpers). It is not a substitute for ordinary validation.

### Path 2: any other `\Exception`

Uncaught generic exceptions (including bugs, DB failures, or code paths that throw `\Exception` without a status code) are caught by the second arm:

```text
$this->logExceptionToFile($e, null);
$this->getResponse()->reset(404);
if ($this->getRequest()) {
    $this->getRequest()->setRuntimeException($e);
}
$this->dispatchError();
```

**This maps every unhandled exception to a `404` response.** The user sees the not-found page; the exception is only visible to whoever reads the log file.

## Why `\Exception → 404` is controversial

This default surprises operators who expect bugs to surface as HTTP `500`:

- Monitoring systems counting 5xx will miss these failures.
- Browsers and API clients get a page that says "not found" for a problem that is actually server-side.
- The `Request::setRuntimeException()` call exposes the exception to the error controller, but only if you read it there.

If your ops story needs distinct 5xx signalling, override the behaviour in your own `run()` wrapper or subclass `Application`. A minimal pattern:

```php
try {
    $app->dispatch();
} catch (\Ufw\Exception\BreakExecution $e) {
    // let framework handle, or re-dispatch explicitly
    $app->run();
} catch (\Throwable $e) {
    $logger->error($e->getMessage(), ['exception' => $e]);
    $app->getResponse()->reset(500);
    $app->dispatchError('internal-error'); // you define action in error controller
}
$app->getResponse()->emit();
```

`dispatchError()` is `protected` on `Application`; a subclass can access it or replace the whole top-level catch.

## The error controller

`dispatchError($action = 'not-found', $controller = 'error')` builds a synthetic `Request` targeting `\Controller\Error::notFoundAction` (etc.) and re-enters `dispatch()` with it:

- Attributes from the previous request (if any) are preserved under the synthetic one.
- If the client's `Accept` prefers `application/json`, the response format switches to `json` before the error action runs - so API clients get a JSON body, browsers get HTML.
- The expected error action names are: `bad-request`, `unauthorized`, `forbidden`, `not-found`, plus any you throw custom status codes for that fall through to `not-found`.

**Today vs core roadmap:** Core does not yet offer a switch of the form “all routes under this prefix are JSON, including errors, regardless of `Accept`.” Until something like that exists, isolate HTTP APIs (e.g. `/api/…` or a dedicated module) and set the response format in middleware or `preDispatch` so success and error handling stay aligned without relying on clients to send `Accept: application/json`. A **possible future core** improvement is described in [Framework analysis - recommendation 8](framework-analysis.md#recommendation-fixed-api-response-format).

You implement the error controller in your app:

```php
namespace Controller;

class Error extends \Ufw\Controller\Base
{
    protected $layout = 'site';

    public function notFoundAction()
    {
        $this->render(); // views/error/not-found.php
    }

    public function unauthorizedAction()    { $this->render(); }
    public function forbiddenAction()       { $this->render(); }
    public function badRequestAction()      { $this->render(); }

    // Optional - if you override run() / dispatchError() for 500s
    public function internalErrorAction()   { $this->render(); }
}
```

Register `error` under `dispatch.controllers` so `checkIfControllerIsRegistered` does not trip when the error request flows back through the router logic.

## Logging

`Application::logExceptionToFile()` appends via `error_log(..., 3, $path)` to the path from `getExceptionLogFilePath()`, which delegates to `Paths::exceptionLogFilePath($appConfig)` (same rules as below). By default that resolves to `Paths::application() . '/log/exceptions.txt'`. Set the path in `config/app.php`:

```php
'logging' => [
    // Absolute, or relative to Paths::application():
    'exception_log_path' => '/var/log/php/myapp-exceptions.txt',
    // Disable file logging (e.g. only Monolog/Sentry):
    // 'exception_log_path' => false,
    // 'exception_log_path' => '',
],
```

From CLI or tests without an `Application` instance, call `Paths::exceptionLogFilePath($configArray)` directly with the same config shape. Subclass `Application` and override `getExceptionLogFilePath()` if the path must ignore config or be computed at runtime (per-tenant log dirs, secrets manager, …).

Each entry includes:

- Timestamp.
- HTTP status (or "uncaught exception; response will be reset to 404").
- Request method + URI, raw `REQUEST_URI`, query string.
- Resolved `module` / `controller` / `action` when available.
- `HTTP_REFERER`, `REMOTE_ADDR`, truncated `User-Agent`.
- Exception code, message, file/line, full stack trace.

This is deliberately **local and file-based**. It works on the simplest PHP-FPM setup without extra infrastructure, but:

- The file grows without bound unless you rotate it (logrotate, `ignore_user_abort` + periodic cleanup, or a cron).
- It is not shipped to a central system. Alerting and search must come from elsewhere.
- The parent directory of the chosen path must exist and be writable by the PHP process (including the default `log/` under `APPLICATION_PATH`).

## Integrating Monolog / Sentry / external logging

Monolog is already a Composer dependency ([`composer.json`](../composer.json)). Wire it once at bootstrap and mirror exceptions into it:

```php
// public/index.php (after autoload, before run())
$logger = new \Monolog\Logger('app');
$logger->pushHandler(new \Monolog\Handler\StreamHandler(
    APPLICATION_PATH . '/log/app.log',
    \Monolog\Logger::INFO
));
\Ufw\Registry::getInstance()->set('logger', $logger);
```

Then subclass `Application` (or wrap `run()`) so both the file log and Monolog see every exception:

```php
class AppApplication extends \Ufw\Application
{
    public function run($request = null)
    {
        try {
            return parent::run($request);
        } finally {
            // parent::run() already handled emit(); this block runs after.
        }
    }

    protected function logExceptionToFile(\Throwable $e, ?int $breakExecutionHttpStatus): void
    {
        parent::logExceptionToFile($e, $breakExecutionHttpStatus); // keep file trail
        $logger = \Ufw\Registry::getInstance()->get('logger');
        if ($logger instanceof \Psr\Log\LoggerInterface) {
            $level = $breakExecutionHttpStatus && $breakExecutionHttpStatus < 500 ? 'warning' : 'error';
            $logger->log($level, $e->getMessage(), [
                'exception' => $e,
                'status' => $breakExecutionHttpStatus,
            ]);
        }
    }
}
```

For Sentry, install `sentry/sdk`, call `\Sentry\init([...])` in bootstrap, and in your overridden `logExceptionToFile()` call `\Sentry\captureException($e)` for exceptions you want escalated (probably: generic `\Exception`, and `BreakExecution` with status ≥ 500 if you extend that).

## Production checklist

- [ ] `display_errors=0`, `log_errors=1` in `php.ini`; errors never leak to browsers.
- [ ] The exception log path (default `APPLICATION_PATH/log/exceptions.txt`, or `logging.exception_log_path`) exists under a writable directory; rotation is configured.
- [ ] You have decided how uncaught `\Exception` is signalled (keep the default 404, override to 500, or wrap).
- [ ] Error controller actions exist for `bad-request`, `unauthorized`, `forbidden`, `not-found`, plus any custom ones you re-dispatch to.
- [ ] Exceptions with sensitive context (passwords, tokens, PII) are scrubbed before logging.
- [ ] External logging (Monolog handler, Sentry, ELK, …) is wired and alerted on.

## See also

- [routing-deep-dive.md](routing-deep-dive.md) - how `decodeRequest` raises 404 before controllers run.
- [middleware.md](middleware.md) - gates that throw `BreakExecution` with 401/403.
- [controllers.md](controllers.md) - the dispatch lifecycle and where errors arise.
- [security-owasp-audit.md](security-owasp-audit.md) - OWASP A10 ("Mishandling of exceptional conditions") context.

---

**Next:** [Database connections](database.md) - `Db`, connection identifiers, and helpers.
