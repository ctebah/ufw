# Views, layouts, partials

← [Documentation index](README.md)

Server-rendered HTML in UFW is plain **PHP scripts** included from **`APPLICATION_PATH/views`**, with an optional fallback to **`Ufw`’s bundled views** (`src/views` inside the package). The **Render** and **Partial** helpers (see [helpers.md](helpers.md)) drive inclusion; **Layout** middleware wraps the action output.

## Path resolution (`Helper\ViewRendererBase::renderHtml`)

1. **`APPLICATION_PATH/views/{script}`** – your app wins.
2. Else **`Ufw/src/views/{script}`** – framework partials (e.g. Bootstrap form snippets).

For **`$this->render()`** with **`$controllerDir === true`** (default), the path is prefixed with the controller **`ident`** (URL-friendly name derived from the controller class), e.g. `news/list.php`. With **`false`**, you pass a path relative to `views/` yourself – **layouts** use this: `layout/site.php`, `layout/_bare.ajax.php`.

If **`$script`** is omitted, the helper uses the **current action** script name (e.g. `list.php`).

## Layout

After the action runs, **`Layout`** middleware (order: [middleware.md](middleware.md)):

- **Full page:** wraps **`$response`’s content** in **`views/layout/{layout}.php`** (default layout name on the controller: **`site`**), passing **`$content`** (and whatever the action already put in the response).
- **AJAX (XHR):** same idea but uses **`layout/_bare.ajax.php`** when the response is still HTML but **`X-Requested-With: XMLHttpRequest`** – no full chrome.

Set **`$disableLayout`** on the controller to skip wrapping for that request.

## Partials

**`$this->partial('foo', $vars)`** resolves to **`views/partials/foo.php`** (no controller prefix). Use for reusable fragments: menus, form rows, flash blocks. UFW ships **Bootstrap 3/4** partials under its **`views/partials`** for form elements – you can override by placing the same relative path in **your** `APPLICATION_PATH/views`.

## Content vs JSON

**`Render::render`** checks **`$response->shouldRenderViewScript()`** (typically **`html`** format). If the response is **`json`**, it **does not** run the PHP view – it merges or sets structured **`$vars`** into the response content path used for JSON encoding later. So the **same** `render()` call can branch on format (see [responses-multiformat.md](responses-multiformat.md)).

## Mental model

**Action** → builds HTML string into **`Response`** → **Layout** wraps with site or bare layout. **Partials** are includes, not a separate template engine – **`extract($vars)`** in scope, standard PHP.

---

**Next:** [Responses: HTML, AJAX, JSON](responses-multiformat.md) – how the same action can return different shapes.
