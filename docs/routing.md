# Routing and dispatch

← [Application bootstrap and registry](bootstrap-and-registry.md) · [Documentation index](README.md)

This page is the essentials: route definitions, match order, controller styles, URL building, and the most common examples. For the full internals - `Router::match()`, `check()` / `provideAttributes()`, custom route classes, `controller_aliases`, extended examples - see [routing-deep-dive.md](routing-deep-dive.md).

## Routes

Routes live in config under `dispatch.routes` (typically `config/app.php`). Each route entry is an array with:

- **`type`** - fully-qualified class name implementing `Ufw\Route\Base` (e.g. `Ufw\Route\RegEx`, `Ufw\Route\Literal`, or your own class).
- **`patterns`** (for `RegEx`) - one or more PCRE patterns matched against the path (query string stripped first).
- **`path`** (for `Literal`) - a single path string (e.g. `/about`), compared after normalization.
- **`defaults`** - default `controller`, `action`, and optional `module`.
- **`constraints`** - optional checks (registered controllers, HTTP method, regex on segments).
- **`urlBuilder`** (for `RegEx`) - one or more strings with `:placeholder` tokens that mirror the patterns; used by `Helper\Url` to rebuild URLs.

The **first matching route wins**. Order in the array matters: specific routes before catch-alls.

Literal-style URLs can be expressed two ways: `Ufw\Route\RegEx` with a single anchored pattern per route, or `Ufw\Route\Literal` so you do not maintain regex / `urlBuilder` for fixed paths.

## After a match

The route implementation fills the `Request` with attributes (`controller`, `action`, `module`, plus named capture groups). `Application::dispatch()` then resolves the PHP class and invokes the action. For the full resolution logic (classic vs method-as-class, aliases, `hasAction()`), see [routing-deep-dive.md](routing-deep-dive.md#resolving-the-php-endpoint-classic-vs-method-as-class).

## Two controller styles

- **Classic** - one class per URL namespace, methods like `listAction()`. Route `action` maps to `list → listAction`.
- **Method-as-class** - the HTTP verb is the class (e.g. `\Controller\Api\News\Get`); handy for small APIs.

Both extend `Controller\Base`. Details: [controllers.md](controllers.md).

## `BreakExecution`

`Ufw\Exception\BreakExecution` is the deliberate **control-flow** mechanism: throw it with a status code and `Application::run()` stops normal dispatch and renders the matching error action (or emits a redirect). It is not a substitute for ordinary validation or middleware. Full behaviour and logging: [error-handling.md](error-handling.md).

```php
throw new \Ufw\Exception\BreakExecution('Not found', 404);
```

## URL building

Route classes implement `buildUrl()`; `Helper\Url` uses route definitions and `urlBuilder` patterns from config to produce URLs. Exact API depends on the route type; keep route names and `urlBuilder` shapes stable once external links depend on them.

## Examples

### Literal-style (one fixed path per route name)

A small, explicit list of URLs (no `/{controller}/{action}` catch-all). One entry per page: a single pattern `^ … $`, targets in `defaults`, matching `urlBuilder`.

```php
return [
    'home' => [
        'type' => '\Ufw\Route\RegEx',
        'patterns' => ['~^/$~'],
        'defaults' => ['controller' => 'index', 'action' => 'index'],
        'constraints' => ['checkIfControllerIsRegistered' => true, 'checkAction' => true],
        'options' => ['allowUriParams' => false],
        'urlBuilder' => ['/'],
    ],
    'about' => [
        'type' => '\Ufw\Route\RegEx',
        'patterns' => ['~^/about$~'],
        'defaults' => ['controller' => 'pages', 'action' => 'about'],
        'constraints' => ['checkIfControllerIsRegistered' => true, 'checkAction' => true],
        'options' => ['allowUriParams' => false],
        'urlBuilder' => ['/about'],
    ],
    'contact' => [
        'type' => '\Ufw\Route\RegEx',
        'patterns' => ['~^/contact$~'],
        'defaults' => ['controller' => 'pages', 'action' => 'contact'],
        'constraints' => ['checkIfControllerIsRegistered' => true, 'checkAction' => true],
        'options' => ['allowUriParams' => false],
        'urlBuilder' => ['/contact'],
    ],
];
```

`Router` walks route names in array order: `home` before `about` before `contact`. To link, pass the route name as the fourth argument to `Helper\Url`:

```php
$this->url([], true, false, 'about');
```

### Literal route (simpler for fixed paths)

Same dispatch, less boilerplate - `path` replaces `patterns` + `urlBuilder`. Dispatch targets live only in `defaults`; no `constraints` unless you want early `checkIfControllerIsRegistered` / `checkAction` validation like on `RegEx` routes:

```php
'about' => [
    'type' => '\Ufw\Route\Literal',
    'path' => '/about',
    'defaults' => ['controller' => 'pages', 'action' => 'about'],
],
```

### Generic `default` route (segments in the path)

Minimal `RegEx` route: `/` or `/news/list` maps to `controller` / `action` via named captures; `urlBuilder` mirrors the pattern so `Helper\Url` can rebuild URLs.

```php
'default' => [
    'type' => '\Ufw\Route\RegEx',
    'patterns' => [
        '~^/$~',
        '~^/(?P<controller>[a-z0-9]+)/(?P<action>[a-z0-9]+)/?$~',
    ],
    'defaults' => [
        'controller' => 'index',
        'action' => 'index',
    ],
    'constraints' => [
        'checkIfControllerIsRegistered' => true,
        'checkAction' => true,
    ],
    'options' => [
        'allowUriParams' => false,
    ],
    'urlBuilder' => [
        '/',
        '/:controller/:action',
    ],
],
```

Named capture `lang` plus defaults (only `patterns` / `defaults` / `constraints` differ from above):

```php
'defaults' => [
    'controller' => 'index',
    'action' => 'index',
    'lang' => 'en',
],
'patterns' => [
    '~^/(?P<lang>en|de|sr)/?$~',
    '~^/(?P<lang>en|de|sr)/(?P<action>[^/]+)/?$~',
],
'urlBuilder' => [
    '/:lang/',
    '/:lang/:action',
],
```

From a controller or view helper, `$this->url()` delegates to `Helper\Url` (route name defaults to `default`):

```php
$this->url(['controller' => 'news', 'action' => 'list']);
$this->url(['lang' => 'de', 'action' => 'about'], true, false, 'default');
```

## Where next

- [routing-deep-dive.md](routing-deep-dive.md) - `Router::match()` internals, `check()` / `provideAttributes()`, custom route classes, `controller_aliases`, HTTP-method constraints, `allowUriParams`, modules, extended examples.
- [controllers.md](controllers.md) - dispatch lifecycle once the route has resolved.
- [error-handling.md](error-handling.md) - `BreakExecution` flow and error controller.

---

**Next:** [Controllers](controllers.md) - `dispatch`, middleware, and where the action runs.
