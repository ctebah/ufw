# Helpers

← [Documentation index](README.md)

Helpers are **small services** attached to the **current controller** (and thus `Request` / `Response` / `Application`). They keep controllers thin: URLs, rendering, assets, flashes, menus, etc. live in **named classes** instead of static utilities scattered across the codebase.

## Resolution (`Application::getHelper`)

When a controller calls **`$this->someHelper()`** (via **`__call`**), or code asks **`$application->getHelper('name', $controller)`**, the framework resolves the class in this order:

1. **`\\Helper\\{Name}`** – **your application** (e.g. `\Helper\MyFeature`). Use this to **override or extend** a built-in helper without forking UFW.
2. **`\\Ufw\\Helper\\{Name}`** – the **framework** copy (`\Ufw\Helper\Url`, `\Ufw\Helper\Render`, …).

`{Name}` is the helper name with **first letter uppercased** (`url` → `Url`).

One **instance per helper name** is cached on `Application`; the **current controller** is injected when the helper is used from a dispatch.

## Base behaviour (`Ufw\Helper\Base`)

- **`setController` / `getController`** – helpers run in the context of the active controller (fallback: current controller from `Registry` if unset).
- **`getRequest()`**, **`getResponse()`**, **`getApplication()`** – same stack as the controller.
- **`__call`** – forwards unknown method calls to the **controller** (so helpers can trigger controller actions when needed).
- **`__get`** – proxies property access to the controller.

Subclass helpers for app-specific behaviour; put them under **`Helper\`** in your autoload map.

## Calling helpers from a controller

The controller’s **`__call`** loads helpers by **method name**: `$this->url()` → helper **`Url`**. Many helpers also implement **`__invoke`** so you can treat them as callables after resolving the name.

Typical examples (framework):

| Helper | Role |
|--------|------|
| **`Url`** | Build paths from route definitions (`url`, `full`, domain). |
| **`Render` / `Partial`** | View scripts and partials; merged data is also what **`Ufw\Response`** uses when the format is **`json`** or **custom** (no view run for those) - see **[responses-multiformat.md](responses-multiformat.md)**. |
| **`Redirect`**, **`Reload`** | HTTP redirects and reloads. |
| **`Assets`** | Named CSS/JS bundles (see [assets-and-jsout.md](assets-and-jsout.md)). |
| **`Placeholder`** | Named string slots in layouts (**any** name - e.g. `title`, `jsReady`; see [assets-and-jsout.md](assets-and-jsout.md)). |
| **`FlashMessenger`**, **`FormErrorsToFlashMessenger`** | Session flash messages and validation errors. |
| **`Menu`**, **`Bootstrap3` / `Bootstrap4` / `Bootstrap5` / `Bootstrap3plain` / `Bootstrap4plain`** | Navigation and form markup helpers - **`Menu`** item keys **`rbac`**, **`env`** (allowed names vs **`Ufw\Envs::runtimeLabel()`**) - see [Form render helpers](#form-render-helpers) below. |

Your app adds **`Helper\*`** classes for domain-specific helpers the same way.

### Form render helpers

Forms resolve a **render helper** via **`Form\Base::getRenderHelperName()`** and config **`form.helper`** (default **`bootstrap3`**). Shared behaviour lives in **`Ufw\Helper\FormRenderHelperBase`**; **`Bootstrap3`**, **`Bootstrap4`**, and **`Bootstrap5`** select the corresponding partial tree (`src/views/partials/bootstrap3/`, `bootstrap4/`, or `bootstrap5/`).

For legacy Bootstrap 3/4 forms, UFW also ships **`Bootstrap3plain`** and **`Bootstrap4plain`**:

- use the same base Bootstrap 3/4 form partials for most elements,
- but render checkbox/radio controls from `bootstrap3plain/` / `bootstrap4plain/` partials without Material Icons glyph markers.

The shipped partials target **Bootstrap** (a common CSS/JS stack); the **`Bootstrap3` / `4` / `5`** class names match that library’s major versions and the `bootstrap3/` … folders under `views/partials/`. The framework does **not** load Bootstrap CSS/JS for you - that remains the app’s concern.

To use another system (e.g. **Bulma**), add a helper class that **extends `FormRenderHelperBase`**, set **`$baseScriptPath`** to a new folder (e.g. `bulma/`), implement the corresponding partials there, and set **`form.helper`** to that helper’s registered name (e.g. `bulma`). You can also place an app-side **`Helper\Bulma`** (or any name) to override or extend a framework helper. The built-in **`Bootstrap*`** helpers are only the default, maintained partial set - not an exclusive option.

### CSRF (`Ufw\Component\Csrf` and `Form\Element\Csrf`)

See [csrf.md](csrf.md) for the full CSRF flow - component (`token` / `validate` / `rotate`), form element, validator, `csrfComponent` option, and non-form usage.

From the helpers perspective: the form render helper you configure under `form.helper` resolves the partial `form/csrf.php` automatically whenever a form contains a `Ufw\Form\Element\Csrf`. No helper-side wiring is required on your part.

## Translation (`Translate` helper)

Call **`$this->translate('Some message')`** from a **view or partial** (`$this` is a helper such as `Render` / `Partial`): unknown methods on `Helper\Base` are forwarded to the **controller**, and the controller’s **`__call`** resolves the **`translate`** helper (`\Ufw\Helper\Translate`). That helper passes the string through a callable stored in the registry:

- **`Registry::getInstance()->set('translator', $callable)`** - typically set once at bootstrap (after config). The callable signature should match **`function (string $message): string`** (same idea as Laminas/Zend `TranslatorInterface::translate`).

If **`translator`** is not set, **`translate()` returns the original string**, so partials can use English message IDs (e.g. `'Page navigation'`) without breaking apps that do not register a translator yet.

For HTML attributes (e.g. `aria-label`), wrap the result in **`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`** so translated text cannot break markup.

## Mental model

Think of helpers as the **controllers’ toolkit**, but **the same helpers are also used from views**: in a view or partial, `$this` is often a helper such as **`Render`** or **`Partial`**, which still runs in the context of the **current controller** (see `Helper\Base`). They are **not** global singletons (except the cached instance per name on `Application`), and they **always know** which controller is handling the request - whether the call stack started in the controller or in a template. That matches how **views** and **URLs** are built in a server-rendered PHP app.

See also [controllers.md](controllers.md) (where helpers plug into `dispatch`).

---

**Next:** [Views, layouts, partials](views-layouts-partials.md) – `Render` / `Partial` paths and layouts.
