# API client component (`Ufw\Component\ApiClient`)

← [Documentation index](README.md)

A **Guzzle-backed** HTTP façade for calling **remote HTTP APIs** from PHP, with optional **form integration** and **error mapping** into UFW forms and flash messages. Lives under **`Ufw\Component\ApiClient`** – not required for normal page rendering; use it when your app talks to another service (REST backend, microservice, etc.).

## Pieces

| Class | Role |
|-------|------|
| **`Client`** | Singleton-style entry; **`MagicTrait`** exposes **`get` / `post` / `put` / …** to Guzzle. |
| **`Base`** (your subclass) | Per-API **facade**: **`getInstance()`**, **`fieldsMap`** (API field ↔ form field), **`raiseExceptionOnApiError`**, **`_run()`** wraps calls with validation and error handling. |
| **`Response`** | Wraps Guzzle response; **`hasException`**, message extraction for errors. |
| **`Exception`** | Carries **`Response`** for 4xx/5xx handling; maps **`errors`** into form elements when a **`Form\Base`** is attached. |

Subclasses of **`Base`** often use **`getSubObject`** pattern via **`__call`** (`getSomething` → namespace sibling class) for multi-resource APIs.

## Typical use

1. Subclass **`Ufw\Component\ApiClient\Base`** (e.g. `\Component\Api\Orders`).
2. Configure **`Client`** with base URI, default headers (auth), etc.
3. Call **`$this->get('…')`**, **`$this->post($url, $body)`**, etc. – same shape as Guzzle, with **optional exception** on HTTP error.
4. Optionally **`setForm()`** with a **`Ufw\Form\Base`** so **422**-style validation payloads can map onto form fields via **`fieldsMap`**.

## Dependency

Requires **`guzzlehttp/guzzle`** (already in `ctebah/ufw` **composer.json**). **Solarium** / Solr are unrelated – this client is **HTTP JSON/XML** services, not search indexes.

## Mental model

**Not** a public API framework for *your* site (that is still **controllers + routes**). This is the **outbound** client: your PHP app as **HTTP client**. For **inbound** JSON, use normal controllers + **`JsonRequest`** / **`JsonResponse`** ([responses-multiformat.md](responses-multiformat.md)).

---

**Next (optional, app pattern):** [Flavors: one codebase, many environments](flavors-multi-app.md) - env + flavor config (not core UFW).

**Done with guides?** See **[Framework analysis](framework-analysis.md)** (review and tradeoffs). Future ideas (e.g. form JSON schema) are noted inline in the guides they relate to - **not** required for evaluating the stack today.
