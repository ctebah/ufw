# Assets and JsOut

← [Documentation index](README.md)

## Assets helper (`Ufw\Helper\Assets`)

Assets are **named bundles** (e.g. `default`, `admin`) configured in app config. Invoking the helper with a name returns an object that exposes:

- **`css()`** – CSS bundle for that name (see `Ufw\Utils\Asset\Css`).
- **`js($position)`** – JS bundle for that name and position (e.g. body bottom via `Ufw\Utils\Asset\Js::POS_BODY_BOTTOM`).

Configuration is read from your app’s `assets` (or related) config keys – paths, versioning, minification flags are **application concerns**; the helper wires **lazy init** and **per-request** asset instances.

## Placeholders

**`Placeholder`** (`Ufw\Placeholder` via the **`Placeholder`** helper) is a **named string slot**: you **`append` / `prepend` / `set`** fragments and **`echo $this->placeholder('name')`** in the layout. The **name is any string** you agree on - there is **no** fixed list.

**Common JS-related names** (convention, not framework magic): `jsReady`, `jsInline`, `jsExt`, `jsRemote` - views and forms push script bits there; the layout prints them once (often near `</body>`). **`JsOut`** (below) **reads** those four buckets when building a combined file.

**Other uses:** e.g. **`title`**, **`headExtra`**, **`meta`**, breadcrumbs - anything you want **controllers or partials** to contribute and **one layout** to output. Same mechanism; only the names differ.

## JsOut (`Ufw\JsOut`)

**JsOut** is a **singleton** for aggregating JS, optionally **concatenating**, **minifying**, and **writing** combined files under a configured web path (e.g. `jsOutStorage` in config). It pulls from the **`jsReady`**, **`jsExt`**, **`jsInline`**, and **`jsRemote`** placeholders only - other placeholder names are ignored by **`JsOut`** (but still work for normal layout output).

Minification uses **[JShrink](https://packagist.org/packages/tedivm/jshrink)** (`tedivm/jshrink`, a Composer dependency of UFW): pure PHP, a practical balance of speed and output size for server-generated bundles. For latest ES syntax or heavy transforms, use Vite/Webpack and serve built assets - JsOut is not a full JS compiler.

Use it when you want **one cached bundle** in production instead of dozens of tags - your app must configure storage paths; set **`serve-minified`** on **`jsOutStorage`** when you want the emitted **`src`** to use the **`.min.js`** suffix.

### `jsReady` execution (`type="module"` by default)

**`jsOutStorage`** (single config block) carries paths, optional minified URL flag, and optional **`readyWrapper`**:

| Key | Purpose |
|-----|---------|
| **`webpath`** | Web path prefix for hashed bundle URLs (required with **`absrootpath`** for JsOut file output). |
| **`absrootpath`** | Filesystem root that joins **`webpath`** (required with **`webpath`**). |
| **`serve-minified`** | If truthy, emitted **`src`** uses the **`.min.js`** suffix. |
| **`readyWrapper`** | Optional. `module` (default when omitted), `classic`, `jquery`, or `none`. The alias `dom` is treated as `classic`. |

- **`module` (default):** `jsReady` is emitted as **`<script type="module">`** (and the concatenated bundle as **`type="module"`** when **`jsOutStorage`** is used). Module scripts are **deferred** by the spec until after the document is parsed; the body is **not** wrapped in an extra IIFE. Code runs in **strict** module scope (no accidental globals from bare assignments).
- **`classic`:** classic **`<script type="text/javascript">`** plus an **IIFE + `DOMContentLoaded`** wrapper. Use when **`jsExt`** + **`jsReady`** must ship as **one non-module** file or snippets assume sloppy global `var`.
- **`jquery`:** classic script plus **`jQuery(function($){ … })`** - requires jQuery on the page.
- **`none`:** no wrapper; classic **`text/javascript`**; you schedule execution yourself.

The **`dom`** value for **`readyWrapper`** is treated the same as **`classic`** (IIFE + `DOMContentLoaded`).

Form helpers store element metadata in **`window.__ufwElementMeta`**, a **`WeakMap`** from DOM node → metadata object. Read it with **`__ufwElementMeta.get(document.getElementById(…))`**, or include the optional helper **`resources/js/ufwmeta.js`**, which defines **`ufwMeta(elementOrId)`** on `globalThis`. That file is not loaded automatically; expose it under your app’s static URL (e.g. copy into `public/js/` or merge into your bundle).

**Bundled `src` tag:** with **`jsOutStorage`**, the combined **`jsExt` + `jsReady`** file is loaded as **`<script type="module" defer src="…">`** when **`readyWrapper`** is **`module`**, otherwise **`<script type="text/javascript" defer src="…">`**. The filename suffix **`.min.js`** vs **`.js`** follows **`jsOutStorage.serve-minified`** (truthy → **`.min.js`**). If you use **`module`**, the **entire** bundle file must be valid **ES module** syntax together with **`jsExt`** (strict mode, no undeclared assignments).

## Mental model

**Assets** = declarative bundles per page family. **JsOut** = optional build step for packaged JS. Neither replaces a front-end build system (Webpack, Vite); they complement server-rendered PHP apps.

---

**Next:** [API client component](api-client-component.md) – outbound HTTP to other services (Guzzle).
