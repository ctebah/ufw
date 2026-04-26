# Middleware

← [Controllers](controllers.md) · [Documentation index](README.md)

Controllers run actions through a **Laminas Stratigility** **`MiddlewarePipe`**: PSR-15-style **`process($request, $handler)`** stages, with **`Ufw\Request`** / **`Ufw\Response`** as the HTTP stack. [controllers.md](controllers.md) summarizes **`dispatch`**; this page is the **pipe** in one place.

## Order (fixed core)

For each action, **`Controller\Base::dispatch()`** builds this list:

```text
middlewaresPre()   →  Dispatch  →  Layout  →  middlewaresPost()
```

- **`middlewaresPre()`** / **`middlewaresPost()`** – you override these in **`Controller\Base`** (usually your app’s **`Controller\Base`**) to return arrays of middleware **class names**, **`Ufw\Middleware\Base`** instances, or **callables** compatible with Stratigility.
- **`Dispatch`** – calls the **action method** on the controller (`listAction`, `Get`, …).
- **`Layout`** – if the response is HTML, wraps buffered output in the controller’s **layout** (or the bare AJAX layout for XHR); see [responses-multiformat.md](responses-multiformat.md).

So anything in **`middlewaresPre`** runs **before** the action; **`Layout`** runs **after** the action body has produced output.

The pipe ends with **`DummyFinal`**, which returns the current **`Response`** object.

## Implementing middleware

Subclass **`Ufw\Middleware\Base`**. The constructor receives the **`Controller\Base`** instance; **`process()`** must call **`$handler->handle($request)`** when the chain should continue, and return a **`ResponseInterface`**.

Built-in examples in the framework:

| Class | Role |
|--------|------|
| **`JsonRequest`** | If **`Content-Type: application/json`**, decodes the body for JSON APIs. |
| **`JsonResponse`** | If **`Accept`** prefers **`application/json`**, sets the response format to JSON (works with [responses-multiformat.md](responses-multiformat.md)). |
| **`Dispatch`** | Invokes the action method (you do not add this yourself – it is always in the middle). |
| **`Layout`** | Applies layout or AJAX shell around captured HTML. |

**JSON vs HTML** for the same action depends on these plus response format - [responses-multiformat.md](responses-multiformat.md).

## Authentication (where it belongs)

**Before `Dispatch`** is the right place for gates that apply to a **whole family** of actions: **session**, **roles**, **bearer** checks, **HTTPS** upgrades, etc. Register them on your app’s **`Controller\Base::middlewaresPre()`** (or a dedicated admin/API base) so individual actions stay thin.

A gate can **`throw BreakExecution`** with status **401** (and optional **`WWW-Authenticate`**) or **302** for redirects - see [error-handling.md](error-handling.md) for the full flow. Implement auth in your app’s **`Middleware\…`** classes; the framework does not ship a built-in Basic-auth middleware.

## Users, roles, and “sky is the limit”

Nothing forces you to cram **identity**, **authorization**, and **policy** into a single class. In practice, production apps often register a **chain** of small middlewares: each one does **one job**, runs in a **fixed order**, and either calls **`$handler->handle($request)`** to continue or stops the pipeline (response already set, **`BreakExecution`**, redirect, …).

Mental model:

1. **Cheap / global first** – JSON body and **`Accept`** negotiation (`JsonRequest`, `JsonResponse`), then whatever your app needs early (page title defaults, locale or copy context, tracing IDs - each its own class if you want clarity).
2. **Identity** – resolve the **user** from session, JWT, or cookies; attach a value the rest of the stack can read (registry, request attributes, or your **`User`** helper).
3. **Step-up or extra checks** – e.g. **two-factor** or **re-auth** for sensitive areas: only runs after you know *who* the user is.
4. **Authorization / policy** – **roles**, tenant, IP allowlists, feature flags, “maintenance mode”: **AccessRestriction**-style middleware runs **after** identity (and often after 2FA) so it can say “this role may not see this module” without duplicating logic in every controller.

Order matters: **user → role (and finer rules)** is a common pattern; you can add or remove stages per base controller (public site vs admin vs API).

Illustrative **`middlewaresPre()`** from a real multi-module app (class names are examples - yours live under **`Middleware\`** or **`App\Middleware\`** depending on autoloading):

```php
protected function middlewaresPre()
{
    return [
        \Ufw\Middleware\JsonRequest::class,
        \Ufw\Middleware\JsonResponse::class,
        \Middleware\Translator::class,
        \Middleware\PageTitle::class,
        \Middleware\User::class,
        \Middleware\TwoFactorAuth::class,
        \Middleware\AccessRestriction::class,
    ];
}
```

Here **`User`** establishes **who** is logged in; **`TwoFactorAuth`** enforces an extra gate when required; **`AccessRestriction`** applies **roles and access rules** before the action runs. Stages you do not need can be dropped or swapped - the framework only requires that your entries fit the pipe contract.

## Callables

**`processMiddlewares()`** also accepts **callables** – they are wrapped in **`CallableMiddlewareDecorator`**. Use for tiny one-off filters; classes are easier to test and reuse.

---

**Next:** [Helpers](helpers.md) – how `render`, `url`, and other toolkit classes resolve.
