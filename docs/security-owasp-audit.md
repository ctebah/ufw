# UFW - OWASP Top 10:2025 security audit (framework lens)

← [Documentation index](README.md)

**UFW is designed to be a sound basis for secure applications.** The core ships deliberate protections and safe patterns: CSRF primitives built on `random_bytes` and `hash_equals`, HTTP header sanitization at the request/response boundary, sensible defaults around forwarded headers, JSON encoding options that reduce some XSS pitfalls when output is mishandled, middleware suited to auth and policy gates, and a database helper layer that escapes literals when used as intended. The dependency stack (e.g. Laminas Diactoros/Stratigility, PSR-7) is standard and maintained.

**That does not remove responsibility from the application.** UFW cannot automatically secure every route, form, query, session, or secret. CSRF must be wired into forms you build; authorization is your middleware and controller logic; SQL safety still depends on avoiding raw concatenation of untrusted fragments; authentication, TLS, logging, and configuration hardening are deployment and app concerns. In practice, **most OWASP Top 10 exposure comes from application and operations choices**, not from the framework failing to offer hooks.

This document maps the [OWASP Top 10:2025](https://owasp.org/Top10/2025/) categories to **ctebah/ufw**: what the framework already supports, what it intentionally leaves to you, and concrete questions for reviewers. It is **not** a penetration test or certification.

**Scope:** UFW core (`src/`, `composer.json`) and documented patterns. Everything under your application tree, infrastructure, and process can widen or narrow real-world risk.

---

## Summary table

| ID | Risk (2025) | UFW built-in support | Primary app responsibility |
|----|-------------|----------------------|---------------------------|
| A01 | Broken access control | Middleware pipeline, `BreakExecution` for 401/403; non-public actions not invokable via reflection | Enforce authorization on every sensitive action and ID-bound resource |
| A02 | Security misconfiguration | Sensible `X-Forwarded-*` filtering defaults; header sanitization at request/response boundaries | Session hardening, HTTPS, secrets outside VCS, least-privilege DB users |
| A03 | Software supply chain failures | Composer-managed dependencies | Pin/audit packages, CI integrity checks, monitor CVEs |
| A04 | Cryptographic failures | `random_bytes` + `hash_equals` in CSRF; JSON output uses `JSON_HEX_*` in places | TLS, password hashing, key management, at-rest encryption |
| A05 | Injection | `Db::quote()` / string-built queries; forms validators | Prepared statements where appropriate; never concatenate untrusted SQL fragments |
| A06 | Insecure design | CSRF & middleware hooks are design-positive | Threat model, abuse cases, rate limits, business-rule checks |
| A07 | Authentication failures | Minimal `Helper\User` (illustrative); middleware doc for auth gates | Real session/JWT/password/MFA implementation |
| A08 | Software or data integrity failures | CSRF `rotate()`; dependency versions in `composer.lock` | Subresource integrity for assets, signed webhooks, supply-chain controls |
| A09 | Security logging and alerting failures | Exception logging in `Application::run`; Monolog dependency | Centralized logs, alerts, no sensitive data in logs, retention |
| A10 | Mishandling of exceptional conditions | Unhandled `\Exception` → 404 + log (can mask server errors) | Map exceptions to correct HTTP status; avoid leaking stack traces to users |

---

## A01:2025 - Broken access control

**Relevance:** Authorization must be explicit in each app. UFW does not ship a role/permission system.

**Framework notes:**

- Pre-dispatch **middleware** (`Controller\Base::middlewaresPre()`, etc.) is the documented place for session, roles, and gates ([middleware.md](middleware.md)).
- Dispatch uses **reflection** so non-public controller methods are not callable as actions - this reduces accidental exposure but is **not** a substitute for authz.
- **`BreakExecution`** supports controlled 401/403/redirect responses.

**Audit questions for your app:**

- Is every state-changing route protected by authentication where required?
- Are object-level checks in place (e.g. “user A may not edit user B’s record”)?
- Are admin/API routes isolated (separate base controller or middleware chain)?

---

## A02:2025 - Security misconfiguration

**Relevance:** Defaults help, but deployment and app config dominate.

**Framework notes:**

- **`ServerRequestFactory`** applies **`FilterUsingXForwardedHeaders::trustReservedSubnets()`** - trust for forwarded headers is limited; still validate proxies in production ([framework-analysis.md](framework-analysis.md)).
- **`HttpHeaderSanitizer`** mitigates header injection issues at the PSR-7 boundary (e.g. multiline header handling with Diactoros).
- **`DBLOG_ENV`**-gated query logging is documented as dev-only; enabling it in production would leak SQL ([`Db` constructor](src/Db/Db.php)).

**Audit questions:**

- Are `session.cookie_httponly`, `session.cookie_secure`, and `session.use_strict_mode` appropriate?
- Are error display and `display_errors` off in production?
- Is `composer.json` / `app.php` free of secrets (use env or secure stores)?

---

## A03:2025 - Software supply chain failures

**Relevance:** UFW pulls in Guzzle, Laminas HTTP stack, Monolog, Negotiation, etc. ([composer.json](../composer.json)).

**Framework notes:** No vendored binaries; standard Composer workflow.

**Audit questions:**

- Commit **`composer.lock`** and verify CI builds with **`composer install --no-dev`** (or your policy).
- Run **`composer audit`** (or equivalent) in CI; subscribe to advisories for direct and transitive deps.
- For internal forks (e.g. **`ctebah/mimey`**), track upstream and branch policy.

---

## A04:2025 - Cryptographic failures

**Relevance:** Crypto for passwords, tokens, and transport is almost entirely application and infrastructure.

**Framework notes:**

- **`Ufw\Component\Csrf`** uses **`random_bytes(32)`** and **`hash_equals()`** for token generation and comparison ([`Component/Csrf.php`](../src/Component/Csrf.php)).
- JSON responses may use **`JSON_HEX_*`** flags to reduce some XSS scenarios when JSON is embedded incorrectly ([framework-analysis.md](framework-analysis.md)).

**Audit questions:**

- Passwords: **`password_hash` / `password_verify`** (or vetted alternatives) with appropriate cost.
- TLS: terminate HTTPS correctly; no mixed content for sensitive flows.
- Secrets: encryption keys and API keys not in source control.

---

## A05:2025 - Injection

**Relevance:** SQL, command, XSS, and header injection are mostly application patterns; UFW offers helpers, not automatic immunity.

**Framework notes:**

- **`Db::quote()`** and helpers like **`subquery()`** build escaped SQL; **`mysqli_real_escape_string`** is used for string literals. **Expression/Condition** objects can embed raw SQL - unsafe if built from user input without validation.
- Form elements and Bootstrap partials often use **`htmlspecialchars`** for option labels and similar ([views partials](src/views/partials/)).
- Raw **`$this->query($sql)`** with concatenated user input remains a critical risk if used.

**Audit questions:**

- Prefer **prepared statements** for complex or high-risk queries if/when you move beyond the helper layer.
- Validate and allowlist anything that becomes a column name, `ORDER BY` field, or dynamic fragment.
- For HTML output, ensure user-controlled strings are escaped in **your** views (framework partials are not exhaustive for custom templates).

---

## A06:2025 - Insecure design

**Relevance:** Framework features (CSRF primitives, middleware ordering) support good design; they do not replace threat modeling.

**Framework notes:**

- CSRF is **opt-in per form** - forgetting it on a POST is a design/process failure ([helpers.md](helpers.md)).
- Middleware ordering (identity before authorization) is documented ([middleware.md](middleware.md)).

**Audit questions:**

- Document trust boundaries (public site vs staff vs API).
- Consider rate limiting, CAPTCHA, and account lockout for auth and sensitive actions at the edge or in app middleware.

---

## A07:2025 - Authentication failures

**Relevance:** UFW’s default **`Helper\User`** is minimal and not a production auth system ([`Helper/User.php`](../src/Helper/User.php)).

**Framework notes:**

- Implement real auth in **app middleware** and user/session models; use **`BreakExecution`** for 401/302 as needed ([middleware.md](middleware.md)).

**Audit questions:**

- Session fixation, regeneration after login, secure cookie flags.
- Brute-force protection, MFA for privileged accounts, secure password reset flows.

---

## A08:2025 - Software or data integrity failures

**Relevance:** Integrity of updates (CSRF), dependencies, and build pipelines.

**Framework notes:**

- **`Csrf::rotate()`** supports one-time use after successful state changes ([`Component/Csrf.php`](../src/Component/Csrf.php)).
- Guzzle and other deps: integrity via Composer and lockfile.

**Audit questions:**

- Webhooks and callbacks: verify signatures.
- Admin actions that install plugins or change config: extra confirmation and CSRF on every step.

---

## A09:2025 - Security logging and alerting failures

**Relevance:** Observable security events need reliable logging and monitoring.

**Framework notes:**

- **`Application::run`** logs unhandled exceptions to a file under the application path (see [framework-analysis.md](framework-analysis.md)); behavior may surprise operators expecting HTTP 500 for all failures.
- Monolog is a dependency - wire it for structured app logging if not already.

**Audit questions:**

- Log auth failures, authorization denials, and admin actions with **correlation IDs** (avoid logging passwords or tokens).
- Ship logs to a system that supports **alerting** and retention policies.

---

## A10:2025 - Mishandling of exceptional conditions

**Relevance:** Error handling can leak information or hide incidents.

**Framework notes:**

- A generic **`\Exception`** in **`Application::run`** is logged, the response is reset to **404**, and **`dispatchError()`** runs ([`Application.php`](../src/Application.php)) - this can **mask 500-class bugs** as “not found” from the client’s perspective while still logging ([framework-analysis.md](framework-analysis.md)).
- **`BreakExecution`** paths map specific status codes to error dispatch (400/401/403/404).

**Audit questions:**

- In production, ensure users see generic error pages; operators see **actionable** logs/metrics.
- Revisit whether mapping all uncaught exceptions to 404 matches your operational and security requirements.

---

## Maintaining this document

When UFW changes (new middleware, DB layer, defaults), update this file and cross-link from [framework-analysis.md](framework-analysis.md) if a short pointer is useful. The split remains: **UFW aims to provide secure primitives**; **each app must use them consistently**. For methodology, refer to the official **[OWASP Top 10:2025](https://owasp.org/Top10/2025/)** list and project-specific threat models.
