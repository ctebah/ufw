# Getting started

← [Documentation index](README.md)

A minimal UFW app from zero: one route, one controller, one view. Target time: 15 minutes. If you want a realistic layout (modules, assets, layouts, forms, DB), skip this page and clone the reference skeleton under [ufw-extras](https://github.com/ctebah/ufw-extras).

This walkthrough assumes PHP 7.4+ (8.2+ recommended) and Composer on your path.

## 1. Create the project

```bash
mkdir my-ufw-app && cd my-ufw-app
composer init --no-interaction --name=local/my-ufw-app --stability=dev
composer require ctebah/ufw
```

## 2. Layout

```
my-ufw-app/
  application/
    config/
      app.php
    controllers/
      IndexController.php
    views/
      index/
        index.php
      layout/
        site.php
  public/
    index.php
  vendor/
```

`application/` holds your code and config; `public/` is the web-server document root; `vendor/` is Composer's.

## 3. Front controller

Create `public/index.php`:

```php
<?php
define('APPLICATION_PATH', dirname(__DIR__) . '/application');

require dirname(__DIR__) . '/vendor/autoload.php';

// Autoload your \Controller\* and \Helper\* from application/
spl_autoload_register(function ($class) {
    $base = APPLICATION_PATH;
    $map = [
        'Controller\\' => $base . '/controllers/',
        'Helper\\'     => $base . '/helpers/',
        'Model\\'      => $base . '/models/',
    ];
    foreach ($map as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
});

(new \Ufw\Application())->run();
```

In production you would use a PSR-4 mapping in `composer.json` instead of the inline loader - the shape above is intentionally minimal.

## 4. Config

Create `application/config/app.php`:

```php
<?php
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

See [config-reference.md](config-reference.md) for every key the framework reads.

## 5. Controller

Create `application/controllers/IndexController.php`:

```php
<?php
namespace Controller;

class IndexController extends \Ufw\Controller\Base
{
    public function indexAction()
    {
        $this->render(['name' => 'world']);
    }
}
```

`render()` with no script name runs the action-matching view (`views/index/index.php`). The array is extracted into the view scope - see [views-layouts-partials.md](views-layouts-partials.md).

## 6. View and layout

`application/views/layout/site.php`:

```php
<!doctype html>
<html>
<head><meta charset="utf-8"><title>My UFW app</title></head>
<body>
<?= $content ?>
</body>
</html>
```

`application/views/index/index.php`:

```php
<h1>Hello, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>!</h1>
```

The layout wraps the action output through the `Layout` middleware automatically - see [middleware.md](middleware.md) for the pipeline.

## 7. Run

Easiest for a quick check is PHP's built-in server:

```bash
php -S 127.0.0.1:8080 -t public
```

Open `http://127.0.0.1:8080/` - you should see `Hello, world!`.

## What you just touched

- **Front controller** (`public/index.php`) and `APPLICATION_PATH` - [bootstrap-and-registry.md](bootstrap-and-registry.md).
- **Route** + `\Ufw\Route\Literal` - [routing.md](routing.md).
- **Controller** + action - [controllers.md](controllers.md).
- **View** + layout - [views-layouts-partials.md](views-layouts-partials.md).

## Next steps

- Add a second route and a second controller to see the dispatch model.
- Wire a form - [forms-and-validation.md](forms-and-validation.md) and [csrf.md](csrf.md).
- Connect MySQL - [database.md](database.md).
- Return JSON from the same action - [responses-multiformat.md](responses-multiformat.md).

For the full linear walkthrough, head back to the [Documentation index](README.md).

## Principles (at a glance)

1. **One application, one config.** `Application` + `Registry`; `APPLICATION_PATH` and `config/app.php` - see [bootstrap-and-registry.md](bootstrap-and-registry.md).
2. **Dispatch.** Routes in config (patterns or literal paths) → controller / action / module; classic or method-as-class controllers.
3. **Database.** `Db::instance()`, explicit `conn_ident`; records declare `$dbconnIdent`.
4. **Model vs Record.** Service layer vs persistable unit; behaviours; Solr when needed.
5. **InfoHash.** Optional secondary index on top of id-keyed maps.
6. **Forms.** Objects with validators; server-side truth.
7. **Assets.** Named CSS/JS bundles; `Placeholder` (arbitrary named slots); `JsOut` for concat/minify in production.
8. **One action, many formats.** JSON / HTML / AJAX fragment via headers, middleware, and `$response->setFormat()`; custom handlers via `Response::registerFormat()`.
9. **Override freely.** Your `Controller\Base`, your records, or plain DTOs where UFW defaults are wrong.

---

**Next:** [Application bootstrap and registry](bootstrap-and-registry.md) - once the toy app runs, see how the bootstrap really works.
