# Controllers

← [Routing](routing.md) · [Documentation index](README.md)

This continues from [routing.md](routing.md): once the **route** (or explicit `Request`) has set **`controller`**, **`action`**, and optional **`module`**, **`Application`** resolves the PHP class, instantiates it with **`Application`**, and calls **`dispatch($action)`**.

## Lifecycle inside `dispatch`

1. **`preDispatch()`** – optional hook before the pipe (controller-local setup). Shared **auth, policy, JSON negotiation** usually live in **middleware** on your app’s **`Controller\Base`** - see **[middleware.md](middleware.md)**.
2. **Middleware pipe** – **`middlewaresPre()`** → **`Dispatch`** (runs the action) → **`Layout`** → **`middlewaresPost()`**; details and built-ins: **[middleware.md](middleware.md)**.
3. **`postDispatch()`** – optional cleanup or logging.

## Two styles (same base class)

- **Classic:** one class per controller, e.g. `\Controller\News`, methods **`listAction()`**, **`detailsAction()`**. The route **action** maps to **`list` → `listAction`** (framework naming rules).
- **Method-as-class:** e.g. `\Controller\Api\News\Get` – the **class** is the endpoint slice; the **method** name is the HTTP verb (`Get`, `Post`, …). Same **`Controller\Base`**, different convention (see **`Application::hasAction()`**).

## Helpers, views, layout

- **`$this->render()`**, **`$this->partial()`**, **`$this->redirect()`**, **`$this->url()`**, etc. come from **helpers** resolved via **`__call`** (`Application::getHelper`). Your app registers **`Helper\*`** or uses **`Ufw\Helper\*`** – see **[helpers.md](helpers.md)**.
- **Response format** - the same **`render()`** can feed **HTML**, **JSON**, or a **custom** wire type: middleware / **`Accept`** / **`setFormat()`** choose **`html`**, **`json`**, or a name you registered with **`Response::registerFormat()`**; the view script runs only when the format is **`html`**. See **[responses-multiformat.md](responses-multiformat.md)**.
- **`$layout`** (default e.g. `site`) and **`$disableLayout`** control which layout **`Layout`** middleware uses; **`getIdent()`** derives a URL-friendly controller id for view paths.

## App-level `Controller\Base`

Almost every real project defines **`Controller\Base extends \Ufw\Controller\Base`** and centralizes **`middlewaresPre()`** / **`middlewaresPost()`**, **`init()`**, and shared helpers - the usual place for **JSON/HTML policy** and cross-cutting rules without forking UFW.

## Escape hatch

Nothing requires your controllers to inherit **`Ufw\Controller\Base`** for the whole app; routing only needs a class that **`Application` can construct and `dispatch()`**. In practice, **subclassing** `Ufw\Controller\Base` is the path of least resistance – see [documentation principles](README.md) on replacing what you dislike.

---

**Next:** [Middleware](middleware.md) – Stratigility pipe, built-ins, `middlewaresPre` / `middlewaresPost`.
