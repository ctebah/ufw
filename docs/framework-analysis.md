# UFW – Framework analysis

← [Documentation index](README.md)

This document reviews `ctebah/ufw` as a PHP micro framework: strengths, gaps, and improvement ideas. It is organized by theme and by major features.

It is a snapshot of the current codebase and behaviour, not a history of changes ("before vs after") and not a marketing sheet. The aim is a straightforward balance: what holds up, what is risky or dated, and where investment would help - including criticism where it is fair. That does not diminish the framework; it keeps this text useful over time.

For how UFW is meant to be used, follow the [documentation index](README.md) (recommended reading order and topic guides). For whether UFW fits your project - FAQ format on usability, extensibility, opinionation - see [evaluation-faq.md](evaluation-faq.md). The two are complementary; this page is code-focused.

On this page: [Positioning](#positioning) · [What works well](#what-works-well) · [What is weak or risky](#what-is-weak-or-risky) · [By category](#by-category) · [Feature-by-feature notes](#feature-by-feature-notes) · [Recommendations](#recommendations-prioritized) · [Implementation quality](#implementation-quality) · [Closing](#closing)

---

## Positioning

UFW sits between a bare PSR stack and a full "kitchen sink" framework. It wires Laminas Diactoros, Stratigility, HttpHandlerRunner, Guzzle, Monolog, and willdurand/negotiation behind a custom Application → Router → Controller → Middleware pipeline, with optional MVC-style views, forms, DB helpers, and an API client. Want a hands-on first impression instead? See [getting-started.md](getting-started.md) or the working demo apps in the companion repo [ufw-extras](https://github.com/ctebah/ufw-extras) (minimal, skeleton, API, i18n, full-CRUD "librarian").

---

## What works well

### Extendability by default

- Zero `final class` declarations in `src/`. Every base class - `Application`, `Controller\Base`, `Form\Base`, `Record\Base`, `Helper\Base`, `Route\Base`, `Db`, `Component\Csrf`, `Component\ApiClient\Base`, even `Singleton` - is open for subclassing. Apps that need different behaviour subclass and replace; they do not patch `vendor/`.
- Helper resolution checks `\Helper\{Name}` (app) before `\Ufw\Helper\{Name}` (framework), so a project-side helper transparently shadows the shipped one without configuration. Same for form partials when you swap the helper class.
- Pluggable seams across the stack:
    - `Response::registerFormat($name, $handler)` - add `csv`, custom JSON envelopes, anything callable.
    - `Route\Base` - write a custom route type alongside `Route\RegEx` and `Route\Literal`.
    - `Form\Element\*`, `Form\Validator\*`, `Form\Filter\*` - new field types and rules.
    - `csrfComponent` Form option - swap the CSRF component (e.g. encrypted-cookie variant) without touching `Form\Base`.
    - `dispatch.controller_aliases` and `dispatch.module.{name}.controller_aliases` - re-point any controller name to a different class.
    - `Db` and `Record\Base` `$dbconnIdent` - multi-connection routing without forking either class.
- `Application` itself can be subclassed. The `Singleton` trait caches one instance per concrete class, so an `App\Application extends Ufw\Application` becomes its own canonical singleton in `Registry`. The same applies to `Db`, `JsOut`, `Registry`, and any other singleton-based component.
- This is the recurring theme of the [documentation index](README.md): defaults, not contracts. Adopt the base, wrap it, or replace it.

### Standards and interoperability

- PSR-7 request/response interfaces (`ServerRequestInterface` on `Request`, `ResponseInterface` on `Response`) keep the door open for middleware reuse - see caveat under *PSR-7 immutability* below.
- Laminas Diactoros / Stratigility / HttpHandlerRunner are well-maintained choices for the HTTP layer and middleware pipe.
- Content negotiation via willdurand/negotiation; `Request::acceptPrefersApplicationJson()` is reused both in middleware and in error dispatch (see *Error handling* below).

### Security-conscious details

- `HttpHeaderSanitizer` mitigates **LP-2023-01** (multiline header termination in Diactoros) by trimming header names/values at the `ServerRequestFactory` boundary and inside `Response::withHeader()` / `withAddedHeader()` - both edges sanitize.
- `ServerRequestFactory::fromGlobals` calls `FilterUsingXForwardedHeaders::trustReservedSubnets()`, a reasonable default for trusting `X-Forwarded-*` only on private networks. Apps should still pin trust per deployment ([security.md](security.md)).
- Default JSON encoding uses `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, reducing XSS surface when JSON output is mishandled in HTML contexts.
- `Ufw\Component\Csrf` ships in core: per-purpose tokens, `random_bytes(32)` generation, `hash_equals` validation, `rotate()` after success, subclassable session prefix. Nothing is automatic for every POST - the app still attaches tokens and calls `validate()`. Full flow in [csrf.md](csrf.md).

### Routing and dispatch

- Two route types ship: `Route\RegEx` (PCRE patterns with named groups) and `Route\Literal` (exact path match). Pluggable through the `Route\Base` contract - apps add their own without forking the framework.
- Dual dispatch model: classic controllers (`Controller\Foo` + `barAction()`) and method-as-class for APIs (`Controller\Foo\Get`). One canonical explanation lives in [controllers.md](controllers.md).
- Failed-match path still preserves named capture groups onto the request via `Router::mergeRegexNamedGroupsIntoRequest()` - so 404 dispatch and templates can read URL context (e.g. `lang`) even when no route matched. Practical touch.
- `ReflectionMethod` checks prevent calling non-public actions by accident.
- Controller aliases and modules support larger apps without one flat namespace. Full reference in [routing-deep-dive.md](routing-deep-dive.md).

### Controller and middleware

- Per-controller middleware (`middlewaresPre` / `middlewaresPost`) composes `Dispatch` (action) and `Layout` in a fixed inner order - easy to reason about for typical PHP apps.
- Layout vs bare AJAX branching keeps full-page HTML separate from fragment responses; the same controller method can serve both.

### Forms and validation

- Form stack (elements, validators, filters) is feature-complete for server-rendered apps and closer to classic Zend/Laminas forms than to raw arrays.
- Partials ship for Bootstrap 3, 4, and 5, plus plain Bootstrap 3/4 variants without Material icon glyphs. Bootstrap 5 partials use inline SVG affordance for checkbox/radio (no icon font dependency). Apps can target a different CSS system by extending `FormRenderHelperBase`.
- CSRF wiring (element, validator, component, `csrfComponent` override) is documented in [csrf.md](csrf.md).

### Database layer

- `Db` centralizes connections (host / port / socket / collation / time zone), logging hooks (`DBLOG_ENV`), and CRUD-style helpers (`insert`, `update`, `delete`, `simple_select_query`) with caller file/line captured for debugging. Practical for small and mid-sized codebases.
- `simple_select_query` accepts a `Record` class name, returning hydrated instances rather than raw arrays - small but ergonomic.

### API client

- `Ufw\Component\ApiClient` wraps Guzzle with magic HTTP verbs, optional `Form\Base` integration (422-style error mapping into form fields via `fieldsMap`), and a per-API subclass façade. Useful as the outbound counterpart to in-app controllers.

### Assets and front-end glue

- Named asset bundles (`assets.*`), `Ufw\Placeholder` for named string slots (`jsReady`, `jsInline`, `jsExt`, `jsRemote`), flash messages, and `JsOut` cover real-world page composition.
- `JsOut` concatenates the four `js*` placeholders, sanitizes embedded `<script>` fragments, minifies via [JShrink](https://packagist.org/packages/tedivm/jshrink), and writes `.js`, `.min.js`, and `.min.js.gz` to `jsOutStorage.absrootpath`. The emitted `<script src>` uses the cached file URL so the browser caches it across requests; `jsOutStorage.serve-minified` picks `.min.js` vs `.js`.
- `jsReady` packaging is configurable via `jsOutStorage.readyWrapper`: `module` (default - `type="module"`, deferred), `classic` (IIFE + `DOMContentLoaded`), `jquery`, or `none`. Modern apps get the deferred-module default without app changes.

### Utilities

- `Ufw\Utils\Stopwatch`, string helpers, `UploadedTmpFile`, and `InfoHash` (id-keyed collection with secondary index) round out day-to-day needs without an extra package per task.

### Companion repo - `ufw-extras`

- Separate repository (not a package) that ships as a reference library: working skeleton and demo apps at different scales - `minimal-app`, `skeleton-app` (site + admin), `api-demo-app` (JSON API), `i18n-app` (Symfony Translation), `librarian-app` (full CRUD with dashboards, loans, reservations, reports).
- Recipes for cross-cutting concerns: `recipes/php/assets` (Phing-driven CSS/JS build), `recipes/php/cron` (dedicated cron module with `LocalOnly` middleware), `recipes/php/I18n`, `recipes/php/autoload`, `recipes/php/database`, `recipes/php/response-format`, `recipes/web-server`.
- Middleware snippets for patterns UFW core deliberately leaves out: `JwtBearerGate`, `DelegatingGate`, `RequestId`, `PageTitlePrefix`, `SoapXmlHeaders`. Each is plain PHP you copy and adapt.
- Value: these are not toy examples. `librarian-app` alone is more representative of a real UFW project than most PHP micro-framework demos - it includes seed scripts, data quality audit tooling, and module-level split (`Staff/*` controllers, staff/member auth components). New adopters get a working, grep-able reference next to the prompt instead of reverse-engineering the docs.
- Caveat: being a demo repo, it is not versioned against `ctebah/ufw`. Pin by commit if you adopt patterns verbatim.

### Hidden gems

Non-obvious wins that pay off in day-to-day work:

- **Form metadata exposed to JavaScript.** Bootstrap 3/4/5 form partials render a `_metadata.php` snippet that binds each form element to a client-side `WeakMap` (`window.__ufwElementMeta`). The app can attach per-element data (parent-child dropdown dependencies, conditional visibility rules, computed field relationships) without inventing `data-*` conventions, duplicating JSON blobs in the DOM, or querying back to the server. The `ufwMeta` helper wires this up; form JS then reads metadata straight from the element reference.
- **Per-form view script.** `Form\Base` exposes a `viewScript` option (`setViewScript($path)`): when set, the entire form layout is rendered from that PHP template instead of the default helper loop. You keep the form object as the single source of truth for fields, filters, validators, and element metadata; you still get custom layout (multi-column, tabs, accordion sections, mixed HTML) without subclassing the render helper. Practical split - in the form you think about fields and rules, in the view script you think about layout.
- **`InfoHash` as a DB result shaper.** Classic ORMs push heavy joins and then normalize the wide row back into objects, spending memory on duplicated columns and CPU on post-join mapping. `InfoHash` inverts that: `set_data($query, RecordClass::class)` hydrates the page rows id-keyed in `$info` (with a secondary index in `$hash`), `aggregate_data($query2, 'id')` merges extra columns from a second narrow query, and `add_array_data($query3, 'id', 'tags')` pushes related rows under a property on each parent. `getTotalCount()` even peels `LIMIT`/`ORDER BY` off the recorded query for paginated views. Memory per row stays tight, SQL stays grep-able, and you avoid Cartesian explosion. See the worked example in [InfoHash](info-hash.md).

---

## What is weak or risky

### PSR-7 immutability vs mutable façade

- Both `Request` and `Response` are mutable façades over Diactoros. `with*` methods replace the inner Diactoros object and return `$this` instead of a fresh wrapper, diverging from PSR-7's immutability contract. Middleware or tests that assume copies will be surprised. Class-level PHPDoc on `Response` is honest about this ("mutable response façade"), but a strict PSR-7 consumer will still misread the type.

### Global state and coupling

- `Registry`, `Application` singleton, `Db::getInstance()`, `Response::getRequest()` reaching back through `Registry`, and the `APPLICATION_PATH` constant all increase hidden coupling and complicate unit testing and parallel-request scenarios (long-running workers, Swoole-style runtimes).
- `Ufw\Container` exists but is a typed lazy factory (one `dataType` per container, reflection `newInstance` per key) - not a generic DI container. Framework resolution still goes through `Registry` and singletons.

### Exception handling and logging

- `Application::run` logs via `logExceptionToFile()` to the path from `getExceptionLogFilePath()` → `Paths::exceptionLogFilePath()` (default `APPLICATION_PATH/log/exceptions.txt`; configurable as `logging.exception_log_path` or by subclassing `Application`). It resets the response to **404** for any generic `\Exception`. That can mask 500-class bugs as "not found" from the client while still logging server-side. The log entry now includes request context (method, URL, route dispatch hints, referer, IP, user-agent), which makes triage easier - but the status-code policy itself remains the controversial part. Override in your app if you want strict 5xx behaviour ([error-handling.md](error-handling.md)).
- Error dispatch uses the same **`Accept` negotiation** as `JsonResponse` (`Request::acceptPrefersApplicationJson()`): if JSON wins, the error controller runs in JSON format. That is the right default for mixed HTML+API apps, but it is listed here because it is a **tradeoff**, not free lunch: `Accept` is **client-controlled** (unlike e.g. a dedicated `/api/…` prefix), so an unusual header ordering (JSON ranked above HTML) can surface a **JSON error payload to something you thought of as a “browser”** hit; **caches and CDNs** must include `Accept` in cache keys (`Vary: Accept`) if the same URL can return both shapes; and **scripts or curl** that omit `Accept` still get **HTML** errors - easy to forget when debugging APIs. None of that is a design bug, but it is operational surface area. See [error-handling.md](error-handling.md).

### Database security and portability

- Queries are built with `mysqli_real_escape_string`-style escaping in helpers, not prepared statements. Safe if consistently applied, but easier to get wrong than parameter binding and not portable to non-MySQL backends.
- Identifiers (table/column names) are interpolated; only values should flow through documented safe paths.

### Authentication

- `Helper\User` in the framework is a thin sketch; real apps replace it with a project-specific helper backed by sessions, JWT, or an identity provider.
- Auth gates belong in `middlewaresPre()` (session required, roles, HTTPS, etc.). CSRF primitives are in core but each state-changing POST must still be wired ([csrf.md](csrf.md)). Session fixation hygiene, cookie flags, and the rest of your threat model remain app concerns ([security.md](security.md)).

### Dependency surface

- Composer pulls Guzzle, Monolog, Laminas (Diactoros / Stratigility / HttpHandlerRunner), Negotiation, Mimey, JShrink. For a "micro" framework that is not tiny; acceptable if you use most of it, otherwise consider splitting optional components (e.g. API client, form stack) into separate packages.

### Testing and quality gates

- No `tests/` directory and no `phpstan.*` / `psalm.*` / `phpcs.*` config in the repository. Regressions in dispatch, `Response`, or `Request` are easy to introduce.

### PHP version

- `composer.json` still declares `"php": ">=7.4"`; 7.4 is EOL. Moving to 8.2+ (or 8.x only) would unlock stricter typing, readonly properties, and constructor promotion in framework code.

---

## By category

### Architecture

| Topic | Assessment |
|--------|-------------|
| Layering | Clear Application → Router → Controller → Middleware → View/Response path. |
| Extension | Helpers and `Registry` allow extension but reduce explicit dependency injection. |
| Error flow | `BreakExecution` for control flow is pragmatic; redirects (3xx) and 401 with `WWW-Authenticate` short-circuit before `dispatchError()`. Status codes must be set consistently - see [error-handling.md](error-handling.md). |
| Layered resolution | `Paths::application()` and `Envs::runtimeLabel()` both follow setter → Registry → constant → fallback. Consistent and unit-testable per-process. |
| Config surface | One lookup table for every key the framework reads - see [config-reference.md](config-reference.md). |

### Security

| Topic | Assessment |
|--------|-------------|
| Headers | `HttpHeaderSanitizer` at request and response edges; Diactoros forwarded-header filters limited to private subnets. |
| XSS | JSON encoding flags reduce mishandled-JSON-in-HTML risk; HTML still depends on escaping in views (framework cannot fully enforce). |
| SQL | Escape-based `Db` layer - prefer prepared statements in new code. |
| Auth | Identity and gates are app-defined (middleware + helpers). CSRF building blocks (`Ufw\Component\Csrf`) are in core; apps must still wire them per form ([csrf.md](csrf.md)). |
| Trust proxies | `X-Forwarded-*` handling must match deployment (TLS terminator, CDN). |
| OWASP mapping | Full per-category review in [security-owasp-audit.md](security-owasp-audit.md); deployment checklist in [security.md](security.md). |

### Performance

| Topic | Assessment |
|--------|-------------|
| Routing | Linear scan over route patterns - fine for modest tables; could add caching or trie/prefix matching at scale. |
| DB | Connection pooling not addressed (typical for PHP-FPM). |
| Output | `ob_*` cleanup in `dispatch()` avoids leaked buffers when actions throw. |
| JS | `JsOut` writes minified + gzipped bundles to disk; subsequent requests skip concat/minify when the cached file already exists. |

### Developer experience

| Topic | Assessment |
|--------|-------------|
| Conventions | Once learned, controller naming, modules, and helper resolution are consistent. |
| Debugging | DB query logging + exception file logging with full request context help triage; `mydie()` is convenient but risky in production. |
| Discoverability | Heavy use of `__call` (helpers) helps writing speed but hurts IDE navigation unless `@method` PHPDoc stays accurate. Class-level PHPDoc is present on most core classes. |

### Code style and consistency

| Topic | Assessment |
|--------|-------------|
| Typing | Mixed: some files use `strict_types` and modern hints; older parts use array shapes and `@` suppression. |
| Naming | Consistent across controllers, routes, helpers, and components. |
| Documentation | Class-level PHPDoc on core types is good; narrative docs (`docs/`) cover intent and recommended use. |

---

## Feature-by-feature notes

| Feature | Summary |
|---------|---------|
| Application | Central lifecycle, config, helper resolution, error dispatch; `getDispatchDefaults()` respects `dispatch.module` + global fallback. |
| Router | Two route types (`Route\RegEx`, `Route\Literal`); order of definitions matters. Failed-match named captures are merged onto the request for 404 context. |
| Request | Rich façade over Diactoros; method-override header supported; `with*` mutates in place (not PSR-7-pure). |
| Response | Mutable façade; pluggable format handlers via `Response::registerFormat()` (defaults: `html`, `json`, `raw`); JSON uses hardened encoding flags; header writes go through `HttpHeaderSanitizer`. |
| Middleware | Stratigility pipe; `DummyFinal` closes the pipeline; auth implemented in app middleware. |
| Controller base | Layout, render, middleware hooks (`middlewaresPre` / `middlewaresPost`); productive defaults. |
| Forms | Server-rendered focus; elements + validators + filters; partials for Bootstrap 3 / 4 / 5 plus plain BS3/4 (no Material icons); BS5 uses inline SVG affordance for checkbox/radio. CSRF via `Form\Element\Csrf` + `Form\Validator\Csrf` + `Ufw\Component\Csrf`. |
| Db | MySQLi-centric convenience (host / port / socket / collation); `simple_select_query` accepts a `Record` class for hydration. Prefer prepared statements in new code. |
| ApiClient | `Ufw\Component\ApiClient` over Guzzle - magic verbs, optional `Form\Base` integration for 422-style error mapping. |
| JsOut / assets | Concatenates `js*` placeholders, sanitizes embedded `<script>`, minifies via JShrink, writes `.js`/`.min.js`/`.min.js.gz`. `readyWrapper` modes: `module` (default), `classic`, `jquery`, `none`. Falls back to plain `<script>` tags when `jsOutStorage` is unset. |
| Views | Partial/layout conventions; framework partials under `Ufw/src/views/` as fallback after app `views/`. Bootstrap 3, 4, 5 form partials maintained in parallel - keep only what you ship. |
| Envs | Layered runtime label resolution (override → `application` config `env` → Registry `env` → `APPLICATION_ENV` → `production`); used by feature gates and `Menu` filtering. |
| Container | Typed lazy factory (one `dataType`, reflection-instantiated entries by key); not a generic DI container. |

---

## Recommendations (prioritized)

1. Add a minimal PHPUnit suite: router match, dispatch to a dummy controller, JSON middleware, one `Response::emit` path, and one `BreakExecution` → `dispatchError` round-trip.
2. Introduce PHPStan (level 5–8) or Psalm to catch interface mismatches early - especially `Request` / `Response` vs PSR-7 strict consumers.
3. Extend the deployment checklist already in [security.md](security.md) with concrete examples (PHP-FPM `expose_php`, `display_errors`, session cookie flags, trusted proxy lists per CDN). The base policy is documented; the worked examples are not.
4. Replace mutable `with*` on `Request` and `Response` with true immutability, or relabel both as "mutable façade" in the type system (e.g. drop `implements ResponseInterface` claims and expose a separate PSR-7 view) so downstream PSR-7 consumers do not get fooled.
5. Prefer prepared statements in new DB access code; optionally wrap `mysqli` / `PDO` with a thin repository layer. Keep the existing escape-based helpers for legacy paths only.
6. Raise minimum PHP to 8.2+ when feasible. Adopt constructor property promotion, enums, and readonly in new code; existing files can stay on the older style until they are touched for other reasons.
7. Ship JSON-schema or PHP-array form schema export from `Form\Base` (already noted as future work in [forms-and-validation.md](forms-and-validation.md)). That would let JS clients render the same forms UFW renders server-side without duplicating definitions.
8. <span id="recommendation-fixed-api-response-format"></span>**Explicit API surface with fixed response format.** Error dispatch today chooses JSON vs HTML from `Accept` (`Request::acceptPrefersApplicationJson()` in `dispatchError`) - good for mixed apps, but pure API trees often want “this URL prefix always JSON” without header discipline or CDN `Vary: Accept` nuance. **Core** could grow a blessed mechanism: e.g. config keys or route/module metadata that force the response format (including errors) before negotiating `Accept`, or a documented first-class pattern for `/api/…` trees. Apps already approximate this with an API module, route prefix, and middleware or `preDispatch` setting format; baking it in would align success and error paths by convention and shrink operational surprises.

---

<a id="implementation-quality"></a>

## Implementation quality

UFW is a single vertical slice of a PHP web stack - HTTP plumbing, dispatch, views, forms, persistence helpers, assets, outbound API glue - held together by one author's set of conventions. End-to-end coherence of this kind is rare in committee-grown frameworks: same tradeoffs repeated until they feel like "the framework way," fewer arbitrary seams between layers. Where the ecosystem moved (header sanitization advisories, JSON encoding flags, JShrink for minify, `type="module"` for deferred scripts) the code has been updated rather than left frozen.

Honest tradeoffs:

- Older patterns (Registry singleton, `APPLICATION_PATH` constant, mutable `with*` on `Request` / `Response`) coexist with newer ones (layered `Paths` / `Envs`, `Component\Csrf`, pluggable `Response::registerFormat()`). Modernization is incremental, not a clean break.
- Quality gates (tests, static analysis) are absent from the repo. The code reads carefully but a regression in `Application::run`, `Router::match`, or `Response::buildInnerResponseObject` would not be caught automatically.
- Scope is owned (classic vs method-as-class controllers, regex + literal routes, server-rendered forms, `Db` escape helpers) rather than absorbed from every other stack. That focus is what keeps the codebase navigable; it is also why apps with very different needs (heavy ORM, GraphQL, fully reactive front-end) will outgrow it.

---

## Closing

UFW is a coherent, pragmatic framework for PHP applications that want routing, controllers, middleware, forms, and MySQL helpers without adopting Symfony/Laravel wholesale. Main gaps: automated tests, static analysis, prepared statements in `Db`, and an authentication opinion (the framework deliberately leaves identity to the app). Policy surfaces the app must own are documented in [security.md](security.md), [error-handling.md](error-handling.md), and [csrf.md](csrf.md). Adding quality gates would meaningfully improve long-term maintainability.

← [Documentation index](README.md) · [Linear guides (Parts A–D)](README.md#full-detail-all-guides)
