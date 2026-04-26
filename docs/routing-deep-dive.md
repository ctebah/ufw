# Routing deep dive

← [Routing and dispatch](routing.md) · [Documentation index](README.md)

Full reference for `Ufw\Router`, `Ufw\Route\Base`, `Ufw\Route\RegEx`, `Ufw\Route\Literal`, `Application::decodeRequest()`, and `Helper\Url`. Useful when debugging routes, constraints, or URL generation, or when writing a custom route class.

For the short overview (basic examples, common shape), start at [routing.md](routing.md).

## Where routes live (config shape)

Routes are loaded from `$config['dispatch']['routes']` (see `Application::getRouter()`). Each **key** is a **route name** (string identifier). The value is a **route definition array** consumed by `Router` and a concrete `Ufw\Route\Base` subclass (typically `Ufw\Route\RegEx`).

| Key | Required | Purpose |
|-----|----------|---------|
| `patterns` | Yes for `RegEx` | Non-empty list of **PCRE** strings. Each is tested with `preg_match()` against the path (see below). Omit for `Literal` (uses `path`). |
| `path` | Yes for `Literal` | Single path, e.g. `/contact`. Normalized the same way as `Ufw\Route\Literal::normalizePath()` (root `/`, otherwise `/segment/...` without a trailing slash). |
| `type` | Yes | Fully qualified class name implementing `Ufw\Route\Base`. Ships with `Ufw\Route\RegEx` and `Ufw\Route\Literal`. |
| `defaults` | Yes | At least `controller` and `action` (and optionally `module` and any keys you merge into the request). |
| `constraints` | No | Optional validation after a pattern matches (HTTP method, registered controllers, per-segment regex, action existence). |
| `options` | For `Base::provideAttributes()` | `allowUriParams` (boolean): if true, parse trailing `key/value/...` path segments and merge into request attributes. |
| `urlBuilder` | For `Ufw\Route\RegEx` | One or more **pattern strings** with `:placeholder` tokens (e.g. `/:lang/:controller/:action`). Used by `buildUrl()` to pick the best pattern and substitute parameters. |

Custom `Ufw\Route\Base` subclasses may add keys; stock `RegEx` expects `urlBuilder` and benefits from `options.allowUriParams`.

## Custom route class (`type`)

You are not restricted to `RegEx` / `Literal`. `Router` instantiates `new $routeDef['type']($router, $routeDef)` and calls `matchRequestPath($path)` → `check($match)` → `provideAttributes()` - the same pipeline as the built-ins.

- **`matchRequestPath($path)`** - default `Base` implementation walks `patterns` with `preg_match`. Override for other matching (prefix trees, generated routes, etc.); return a **PCRE-style match array** (include index `0` for the consumed path span if you rely on `options.allowUriParams` in `provideAttributes()`), or `false`.
- **`buildUrl(...)`** - required; implement URL generation for your config shape (you can reuse `prepareBuildUrlParams()` / `finalizeBuildUrlFromSegments()` from `Base` where they fit).
- **`check()`** / **`provideAttributes()`** - inherited from `Base`; override only if you need different constraint or attribute behaviour.

Document any custom keys your class reads in your app's config docs. In a route definition, set `type` to the FQCN (e.g. `'\My\App\Route\Acme'`) and register the class in Composer autoload.

## Path vs query string

`Router::match()` reads `REQUEST_URI`. If it is empty or missing, it is treated as `/`. Everything after `?` is **stripped** before matching - only the **path** is matched against `patterns`. Query parameters remain available through the normal PSR-7 request (`getQueryParams()`).

## Matching order ("first match wins")

1. **Route order** - `foreach` over `dispatch.routes` preserves array order. The first route whose logic succeeds becomes the current route.
2. **Matching within a route** - `Route\Base::matchRequestPath()` decides (default: each `patterns` entry in order with `preg_match()`). Custom `type` classes replace that logic as needed.
3. **Stop** - on success, `Router` sets `currentRouteName`, `currentRoute`, calls `provideAttributes()`, and stops.

Put specific routes before catch-alls (e.g. `/admin/...` before `.*`).

## After `preg_match`: `check()` and `provideAttributes()`

When a pattern matches, `$match` is PCRE's match array (numeric + named captures).

### `Route\Base::check($match)`

- If there is no `constraints` key, `check()` returns `true`.
- **`module`** - if the match contains a non-empty `module` group, it sets the provisional module for controller resolution.
- **`controller`** - provisional controller name is taken from `$match['controller']` or `defaults['controller']` (with `@`-silenced edge cases in code).
- **`checkIfControllerIsRegistered`** - if set, the provisional controller must appear in `Application::getDispatchControllers($module)` (from config `dispatch.controllers` or `dispatch.module.{name}.controllers`, including entries implied by `controller_aliases`).
- **`httpMethod`** - if set, the request method (see `Request::getMethod()`, which honors `X-HTTP-Method-Override`) must be in that list.
- **`action`** - if the match provides `action`, or `defaults` define `action`, `checkAction` (when enabled) uses `hasAction()` (reflection: public method on the resolved controller class). If there is no action from match or defaults, `check()` can fail when `$hasAction` stays false.
- **Any other `constraints` key** (not `checkIfControllerIsRegistered`, `checkAction`, `httpMethod`) is treated as the name of a capture/default; its value must be a **regex string** run with `preg_match()` against `$match[$key]` or `defaults[$key]`.

If `check()` returns `false`, the pattern is rejected; `Router` tries the next pattern or route.

### Pattern matched but `check()` failed: named groups preserved

If `preg_match()` succeeds but `check()` fails, `Router::mergeRegexNamedGroupsIntoRequest()` merges named capture groups onto the router's `Request` attributes. That way `Application::decodeRequest()` / `dispatchError()` (404) still see URL context such as `lang` - useful for localized error pages or logging.

### `provideAttributes()` (successful route)

On success, `provideAttributes($request, $path, $match)`:

- Sets `controller` and `action` from provisional names computed during `check()`.
- Collects named regex groups except numeric keys and the reserved `controller` / `action` keys into `$params`.
- If `options['allowUriParams']` is true, parses the remainder of the path after the matched span as alternating `key` / `value` segments (URL-decoded); duplicate keys become arrays of values (merged).
- Merges into the request: `$values + $params + $defaults` (PHP `+` preserves left-hand keys on duplicates), then `setDefaults($defaults)`.

Implementations of `Ufw\Route\Base` must ensure `options` (and any other keys they read) exist or are guarded - the stock `RegEx` path assumes `options` is present where `allowUriParams` is read.

## Resolving the PHP endpoint (classic vs method-as-class)

`Route\Base::getControllerClass()` (and `Application::hasAction()`) implement the same two conventions documented in [controllers.md](controllers.md):

1. **Method-as-class** - if `\Controller\{Module\}\{Controller}\{Action}` exists (action segment turned into a class name), the HTTP method name (`Get`, `Post`, …) is the reflected method to run.
2. **Classic** - otherwise `\Controller\{Module\}\{Controller}` with `{action}Action` (e.g. `list` → `listAction()`).

## `controller_aliases`

Optional associative map: URL-facing controller id → canonical id used when resolving `\\Controller\\…` class names. Applied in `Route\Base::getControllerClass()` and `Application::hasAction()` after reading `controller` from the route / request (global: `dispatch.controller_aliases`; per-module: `dispatch.module.{name}.controller_aliases`).

- `$this->url(['controller' => 'blog', …])` and path segments can expose the short name `blog` while the PHP class lives under the canonical segment (e.g. `News`).
- `dispatch.controllers` lists logical names that may appear in URLs. On bootstrap, `Application::setDispatchControllers()` also merges the keys of `dispatch.controller_aliases` into that list so `checkIfControllerIsRegistered` accepts alias ids without duplicating them by hand in `controllers`.

```php
'dispatch' => [
    'controllers' => ['index', 'news'],
    'controller_aliases' => [
        'blog' => 'news',   // /…/blog/… → \Controller\News
    ],
    // …
],
```

Per-module aliases under `dispatch.module.{name}.controller_aliases` work the same way.

## `Ufw\Route\RegEx` and URL building

`RegEx::buildUrl($params, $reset, $withQS, $encode)` (used by `Helper\Url`) expects `urlBuilder`: one or more strings containing `:name` placeholders. Internally, patterns are compiled to placeholder lists; `getUrlBuilderString()` chooses the pattern whose placeholders are **all** satisfiable from current `$params`, preferring the pattern that matches the most placeholder keys.

- `$reset` - if true, starts from defaults / params only; if false, merges with current request attributes.
- Controller/action equal to defaults are omitted from the built path when possible.
- With `options.allowUriParams`, remaining parameters become `/key/value/...` segments; array values emit repeated `key/value` pairs.
- `$withQS` - if true, merges current query string with leftover params into `?…` (otherwise leftover scalar params can appear in the query depending on `allowUriParams`).

`Helper\Url`: `url($params, $reset, $withQS, $routeName, $encode)` loads `getRouter()->getRoute($routeName)` (default name `default`), or `getCurrentRoute()` when `$routeName` is falsy, then calls `buildUrl()`. `full()` prepends scheme and host.

## Examples (extended)

**Literal-style + catch-all** - many sites combine explicit routes with a last route whose pattern is broad (e.g. `/{controller}/{action}`) so unlisted paths still dispatch. Put every `~^/…$~` route above that `default`.

**HTTP method** - only match when the verb is in the list (`Request::getMethod()`; `X-HTTP-Method-Override` counts):

```php
'api_items' => [
    'type' => '\Ufw\Route\RegEx',
    'patterns' => ['~^/api/items$~'],
    'defaults' => ['controller' => 'api', 'action' => 'items'],
    'constraints' => [
        'httpMethod' => ['POST', 'PUT'],
        'checkIfControllerIsRegistered' => true,
        'checkAction' => true,
    ],
    'options' => ['allowUriParams' => false],
    'urlBuilder' => ['/api/items'],
],
```

**Segment regex** - any extra `constraints` key (other than the built-ins) is treated as a named capture name; its value is a regex tested against `$match[$key]` or `defaults[$key]`. The `patterns` must define the same name (e.g. `(?P<lang>…)`).

```php
'constraints' => [
    'checkIfControllerIsRegistered' => true,
    'checkAction' => true,
    'lang' => '~^(en|de)$~', // must match capture or default for `lang`
],
```

**`allowUriParams`** - after the matched part of the path, alternating `key/value` segments become request attributes (duplicate keys merge into an array). Example path `/news/list/page/2` if the regex stops after `list`:

```php
'patterns' => ['~^/(?P<controller>[^/]+)/(?P<action>[^/]+)~'],
'options' => ['allowUriParams' => true],
// remainder "page/2" → attribute page = "2"
```

**Two routes, fixed order** - register a narrow route before a catch-all:

```php
'dispatch' => [
    'routes' => [
        'admin' => [ /* patterns only ~^/admin/… */ ],
        'default' => [ /* broad pattern last */ ],
    ],
],
```

**Throwing `BreakExecution` in an action** (same pattern as redirects / early exit):

```php
throw new \Ufw\Exception\BreakExecution('Not found', 404);
```

See [error-handling.md](error-handling.md) for how `run()` turns this into an HTTP response.

## Application: `decodeRequest()`, `dispatch()`, and explicit requests

`Application::run()` → `dispatch()` with no argument → `decodeRequest()`:

1. `$this->getRouter()->match()` - populates the router's `Request` when a route matches.
2. If no route matches, `setRequest()` is called and `BreakExecution 404` is thrown.
3. If a route matches, `decodeRequest()` returns that same router `Request`.

`dispatch()` then:

- If `$request` was not passed in and `getRouter()->getCurrentRoute()` is set, it uses `getCurrentRoute()->getControllerClass()` and `getActionMethod()` without calling `hasAction()` again (the route already passed `check()`).
- If `$request` was passed explicitly (tests, `dispatchError()`, sub-requests), it resolves via `hasAction(controller, action, method, module)` - on failure, **404** or **405** (`BreakExecution`) depending on whether the controller class exists but the method does not.

So: router match trusts `Route\Base::check()`; manual `Request` goes through `hasAction()` again.

## Modules

- Regex may capture `module`; `defaults['module']` can also set it.
- `dispatch.module.{name}.controllers`, `defaults`, and `controller_aliases` follow the same layout as top-level `dispatch`, scoped to that module (`Application::getDispatchDefaults($module)`, `getDispatchControllers($module)`).

## Practical checklist

- Order routes from most specific to general.
- Order `patterns` inside a route from stricter to looser if multiple patterns compete.
- Use `constraints` to avoid overly broad regexes "stealing" URLs (HTTP method, `checkIfControllerIsRegistered`, segment regex).
- Keep `urlBuilder` patterns consistent with `patterns` so `Helper\Url` generates the same shape of URL your router matches.
- Once external links depend on URLs, treat route names and `urlBuilder` as stable API.

---

**Related:** [routing.md](routing.md) (essentials) · [controllers.md](controllers.md) (dispatch lifecycle) · [error-handling.md](error-handling.md) (`BreakExecution` and status codes).
