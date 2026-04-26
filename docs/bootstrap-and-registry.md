# Application bootstrap and registry

← [Documentation index](README.md)

UFW is a **library**: your repo owns the **front controller** (e.g. `public/index.php`), sets the **application root** (see **`Ufw\Paths`** / **`APPLICATION_PATH`** below), loads Composer’s autoloader, then constructs **`Ufw\Application`** and calls **`run()`**. Nothing in the package replaces that one-time setup – but the pattern is always the same mental model.

## Typical bootstrap

1. **Application root path** – usually **`define('APPLICATION_PATH', …)`**, or **`Registry::getInstance()->set('application_path', …)`**, or **`Paths::setApplicationPath(…)`** before **`Application`** (see **`Ufw\Paths`**).
2. **Autoload** – `vendor/autoload.php` (Composer) so `Ufw\*` and your `\Controller\*`, `\Helper\*`, … resolve.
3. **`Application`** – `new \Ufw\Application()` (or **`Application::getInstance()`** if you prefer the singleton entry; both end up in the same lifecycle).
4. **`run()`** – matches the request, dispatches a controller, emits the response.

Config is loaded inside **`Application::__construct()`** from **`Paths::application() . '/config/app.php'`** unless you **`setConfig()`** earlier. Dispatch defaults and route tables come from that array.

Inside the package, paths are resolved with **`Ufw\Paths::application()`** (not a different subclass - callers always use this class). Resolution order: **`Paths::setApplicationPath($dir)`** if you set it; else Registry **`application_path`** if present; else the **`APPLICATION_PATH`** constant. That avoids scattering the bare constant in framework code while still letting you choose **one** mechanism in bootstrap (or tests via **`setApplicationPath`**).

**Runtime environment label** (e.g. `development` vs `production`) uses the same idea in **`Ufw\Envs::runtimeLabel()`**: optional **`Envs::setRuntimeEnvLabel()`** override, then **`env`** in **`Application`** config (once bootstrapped), then Registry **`env`**, then constant **`APPLICATION_ENV`**, else **`production`**. Helpers such as **`Menu`** compare menu item **`env`** filters against that string.

## `Registry`

**`Ufw\Registry`** is a **process-wide key–value store** (extends **`ArrayObject`**, **`Singleton`**). When **`Application`** is constructed, it registers itself:

```text
Registry::getInstance()->set('application', $this);
```

Code that does not have a controller handy (records, some helpers, routes) often resolves the app via **`Registry::getInstance()->get('application')`**. You may **`set()`** other shared objects your app agrees on – the pattern is explicit keys, not a grab bag of globals.

**`Registry`** also supports **`SplObserver`**-style **`attach` / `notify`** when a key is **`set`** – rarely needed, but there if you want side effects on registration.

## Singletons in UFW

The **`Singleton`** trait gives **`getInstance()`** / **`newInstance()`** with **one cached instance per class** (and per constructor **argument fingerprint**). **`Application`** and **`Registry`** use it; so do **`Db`**, **`Ufw\Utils\Strings`**, and other framework services.

**Convention:** prefer **`Application`** as the façade for request, response, helpers, and config; use **`Registry`** when something must be reachable without threading **`Application`** through every constructor. Avoid turning the registry into an implicit service locator for everything – keep keys few and documented in your app.

---

**Next:** [Routing](routing.md) – how URLs become controller actions.
