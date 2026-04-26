# Security

← [Documentation index](README.md)

Short overview: what UFW provides, what stays the application's job, and a deployment checklist. For the full category-by-category mapping against OWASP Top 10:2025, see [security-owasp-audit.md](security-owasp-audit.md). For vulnerability reporting, see [SECURITY.md](../SECURITY.md) at the repo root.

## What UFW provides

- **CSRF primitives** - `Ufw\Component\Csrf` (session synchronizer tokens with `random_bytes` + `hash_equals`), form element + validator. See [csrf.md](csrf.md).
- **Header sanitization at the PSR-7 boundary** - `HttpHeaderSanitizer` trims header names/values in `ServerRequestFactory` and when setting response headers, addressing multiline header termination cases in Diactoros.
- **Forwarded header trust defaults** - `ServerRequestFactory::fromGlobals` uses `FilterUsingXForwardedHeaders::trustReservedSubnets()`, limiting trust of `X-Forwarded-*` to private networks.
- **JSON output flags** - `JSON_HEX_*` options reduce some XSS scenarios when JSON ends up mishandled inside HTML.
- **Non-public actions not invokable** - dispatch uses `ReflectionMethod` so private/protected controller methods cannot be reached as HTTP actions.
- **Middleware pipeline** - the documented place for identity, two-factor, roles, HTTPS upgrades, and rate limits. See [middleware.md](middleware.md).
- **Control-flow `BreakExecution`** - 401/403/404/redirect responses via a single exception. See [error-handling.md](error-handling.md).

## What stays the application's job

- **Authentication.** UFW's `Helper\User` is a minimal sketch; implement real session/JWT/password/MFA in your app.
- **Authorization.** No role/permission system ships; object-level checks ("user A cannot edit user B's record") are your responsibility.
- **CSRF wiring per form.** Nothing auto-validates POSTs. Attach the element, call `rotate()` on success, validate on non-form flows. See [csrf.md](csrf.md).
- **SQL safety.** `Db` helpers escape string literals, but identifiers and raw `query($sql)` with concatenation are not guarded. Prefer prepared statements for new code.
- **Session hardening.** Cookie flags (`HttpOnly`, `Secure`, `SameSite`), regeneration on login, fixation prevention.
- **TLS and HTTP headers.** HTTPS termination, HSTS, CSP, `X-Content-Type-Options`, cookie scope.
- **Secret handling.** Keys and passwords outside version control (env vars, secret stores); least-privilege DB users.
- **Exception response policy.** The default maps every uncaught `\Exception` to `404`; decide whether that matches your ops model. See [error-handling.md](error-handling.md).
- **Logging and alerting.** File-based exception logging (default `log/exceptions.txt` under the app root; path resolved by `Paths::exceptionLogFilePath()`, driven by `logging.exception_log_path` or overridden via `Application::getExceptionLogFilePath()`) is a starting point - wire Monolog/Sentry/ELK for real observability.

## Deployment checklist

### PHP and framework

- [ ] PHP 8.2+ if feasible (minimum supported is 7.4 per [composer.json](../composer.json)).
- [ ] `display_errors = 0`, `log_errors = 1`, error pages reviewed so stack traces never reach the browser.
- [ ] `DBLOG_ENV` constant is **not** defined in production (it enables SQL log dump at shutdown - dev only). See [database.md](database.md).
- [ ] `composer install --no-dev` (or equivalent) in the production build.
- [ ] `composer.lock` committed and reproduced in CI.
- [ ] `composer audit` (or dependency scanner) runs on each build.

### Session and cookies

- [ ] `session.cookie_httponly = 1`.
- [ ] `session.cookie_secure = 1` behind HTTPS.
- [ ] `session.use_strict_mode = 1`.
- [ ] `session.cookie_samesite = Lax` (or `Strict` for admin sessions).
- [ ] `session_regenerate_id(true)` on login.

### Transport and headers

- [ ] HTTPS terminated at the edge; no mixed content on sensitive flows.
- [ ] HSTS enabled once you are confident in HTTPS-only operation.
- [ ] Content Security Policy (CSP) sized to your frontend - form helpers do not emit inline handlers by default.
- [ ] `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` set at the web-server or middleware level.
- [ ] Trusted proxies configured explicitly if you rely on `X-Forwarded-*` - defaults trust only reserved subnets.

### Application patterns

- [ ] CSRF element (or manual `Csrf::token()` + `validate()`) on **every** state-changing POST; `rotate()` after success. See [csrf.md](csrf.md).
- [ ] Authentication gate in your app's `Controller\Base::middlewaresPre()` (or per-module base). See [middleware.md](middleware.md).
- [ ] Authorization (roles, tenant, object ownership) evaluated in middleware or at the start of each action; never assume the route alone is enough.
- [ ] Every rendered user-controlled string is escaped with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` or a helper that does the same.
- [ ] SQL identifiers (table, column, `ORDER BY`) come from an allowlist - never from request input.
- [ ] File uploads validated by MIME sniffing, size, and destination path outside the document root where possible.

### Logging and ops

- [ ] `APPLICATION_PATH/log/` exists, is writable, and rotates (logrotate or similar).
- [ ] Monolog (already a dependency) configured with structured output to your log aggregator. See [error-handling.md](error-handling.md).
- [ ] Alerts on auth failures, authorization denials, and unusual spikes in `BreakExecution` status codes.
- [ ] Secrets and tokens never logged (scrub before writing).

## See also

- [SECURITY.md](../SECURITY.md) - private vulnerability reporting process.
- [security-owasp-audit.md](security-owasp-audit.md) - full OWASP Top 10:2025 mapping against framework defaults and application duties.
- [csrf.md](csrf.md) - CSRF component, form element, and validator.
- [error-handling.md](error-handling.md) - exception logging and HTTP status behaviour.
- [framework-analysis.md](framework-analysis.md) - broader "what works well / what is risky" view, including security.

---

**Next:** [Documentation index](README.md).
