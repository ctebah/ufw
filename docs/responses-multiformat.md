# One action: full page, AJAX fragment, or JSON

← [Documentation index](README.md)

**Multiformat responses** (same URL / action, different shapes over HTTP) are the topic of this guide - often called **content negotiation** or **multi-format** in other frameworks.

UFW is built so **the same controller action** can serve **a normal HTML page**, an **AJAX HTML fragment**, or **JSON**, depending on **headers and response format** – you can grow a **site and an API** from the same endpoints instead of duplicating controllers.

## The levers

| Mechanism | Effect |
|-----------|--------|
| **`Accept: application/json`** | Middleware **`JsonResponse`** negotiates with `willdurand/negotiation` and sets **`$response->setFormat('json')`** when JSON wins over `text/html`. |
| **`JsonRequest`** | If **`Content-Type: application/json`**, the body is decoded into **`parsedBody`** (typical for JSON POST/PUT APIs). |
| **`$request->isXmlHttpRequest()`** | True when **`X-Requested-With: XMLHttpRequest`** (jQuery and many JS stacks set this). |
| **`$response->setFormat(…)`** | Explicit override (`'json'`, `'html'`, …) when negotiation is not enough. |

## What the user sees

- **Full HTML:** format **`html`**, **not** an XHR request → **`Layout`** middleware wraps content in the controller’s **site layout** (or similar).
- **AJAX fragment:** format **`html`**, **is** XHR → **`Response::mayHaveHtmlLayout()`** is false; **`Layout`** still renders view output but uses the **bare AJAX** layout (`layout/_bare.ajax.php` pattern) – **no full chrome**, just your partial HTML.
- **JSON:** format **`json`** → response body is built as **JSON** (`JsonResponse` path in `Response::buildInnerResponseObject`); no HTML layout pass for that format.

### Custom formats (`Response::registerFormat`)

Built-in handlers for **`html`**, **`json`**, and **`raw`** (unknown format name → raw stream) live in a static map; **`registerFormat()`** replaces or adds an entry. Each handler returns a **PSR-7** **`ResponseInterface`** (e.g. **`HtmlResponse`**, **`JsonResponse`**, or **`Laminas\Diactoros\Response`** with a body stream).

```php
\Ufw\Response::registerFormat('csv', function (mixed $content, \Ufw\Response $response): \Psr\Http\Message\ResponseInterface {
    // $content = what render() merged. Use $response->withHeader(...), then return a Diactoros response with body.
    return $response->getProvisionalResponse()->withBody($stream);
});
```

The handler receives the same **`$content`** **`Render`** would set, plus the facade so you can call **`withHeader()`** (e.g. **`Content-Disposition`**). Use **`getProvisionalResponse()`** when wrapping the body in a stream while keeping status and headers.

**Implementation:** default **`html`** / **`json`** / **`raw`** handlers, the static handler map, **`registerFormat()`**, and **`buildInnerResponseObject()`** live in **[`src/Response.php`](../src/Response.php)** (`Ufw\Response`).

## Wiring it

Add **`JsonRequest`** and **`JsonResponse`** to **`middlewaresPre()`** on your base controller, then any app-specific middleware - see [middleware.md](middleware.md). **Browsers** default to HTML; clients that send **`Accept: application/json`** / **`Content-Type: application/json`** get the negotiated behaviour above.

## Design intent

**Gradual enhancement:** same URL for humans (full layout) and scripts (JSON or fragment). **Not** a substitute for versioning a public API – but for **admin panels, internal tools, and hybrid pages**, it keeps **one mental model** for authorization and validation.

---

**Next:** [Error handling](error-handling.md) - `BreakExecution`, `dispatchError`, exception logging.
