# UFW documentation

These guides explain how UFW is meant to be used - intent and mental model, not a full API reference.

**Intent over obscurity.** Using the framework should not feel like an IQ test. Each guide spells out what we mean (and what we do not), with enough context for educated choices - adopt a default, wrap it, or replace it - instead of rewarding guesswork.

**Defaults, not dogma.** The framework ships base classes (`Controller\Base`, `Record\Base`, `Form\Base`, …) as shortcuts. Subclass, wrap, or replace what does not fit. The recurring theme is extendability; "the UFW way" is a starting point, not a contract.

**Vendor-neutral.** Text under `docs/` does not name specific customer sites, employers, products, or internal project codenames - not as examples and not as credentials. Illustrations use generic situations only ("internal vs public", unnamed tenants). That keeps the manual reusable.

**Choosing whether to adopt UFW?** Start with [Evaluation FAQ](evaluation-faq.md) - TL;DR table, poor/strong fit, then topic FAQs (license, hosting, ecosystem, technical fit). It complements [Framework analysis](framework-analysis.md) (code-focused review).

**Want to see real apps, not just docs?** The companion repo [ufw-extras](https://github.com/ctebah/ufw-extras) ships working demo apps (minimal, skeleton, API, i18n, full-CRUD "librarian"), recipes for cross-cutting concerns (assets build, cron, i18n, autoload), and middleware snippets. Open it beside this index - most "how do I structure X" questions answer themselves there.

---

## Choose how deep to go

### Quick taste (~15 min)

You want to feel what UFW is about - not commit to the full walkthrough.

| Step | Read | Why |
|------|------|-----|
| 1 | [Getting started](getting-started.md) | Running "Hello world" in about 15 minutes. |
| 2 | [Project README](../README.md) | Tone, who it's for, what it is not. |
| 3 | [Framework analysis](framework-analysis.md) - Positioning + skim "What works well" | Stack in one page: Laminas, routing, forms, DB, honest scope. |
| 3b | [Evaluation FAQ](evaluation-faq.md) (optional) | TL;DR, poor/strong fit, license & hosting - before you read the rest. |

Optional deep-cuts while you are shopping: [Responses: HTML, AJAX, JSON](responses-multiformat.md) (same controller → full page / fragment / JSON) · [Flavors (multi-app)](flavors-multi-app.md) (one tree, many environments or products; not core UFW).

Stop here if you only needed orientation.

### Full detail (all guides)

Follow Parts A → D in order (see tables below) and use the `Next:` link at the bottom of each guide. Use the [index](#index-all-guides-az) to jump by topic when you already know what you need.

---

## Full walkthrough (Parts A–D)

Linear path from bootstrap to outbound HTTP.

### Part A – From URL to HTML (or JSON)

| # | Guide | What you get |
|---|--------|----------------|
| 1 | [Application bootstrap and registry](bootstrap-and-registry.md) | `APPLICATION_PATH`, `Application`, `Registry`, singletons. |
| 2 | [Routing](routing.md) | Routes, matching, URL building - essentials. |
| 2a | [Routing deep dive](routing-deep-dive.md) | `Router` internals, `check()` / `provideAttributes()`, custom routes, aliases, modules (reference). |
| 3 | [Controllers](controllers.md) | `dispatch` lifecycle, `preDispatch` / `postDispatch`, classic vs method-as-class. |
| 4 | [Middleware](middleware.md) | Stratigility pipe, JSON/layout, auth chains, custom classes. |
| 5 | [Helpers](helpers.md) | `Helper\*` vs `Ufw\Helper\*`, resolution, Render / Url / Assets. |
| 6 | [Views, layouts, partials](views-layouts-partials.md) | `APPLICATION_PATH/views`, layouts, partials, framework fallbacks. |
| 7 | [Responses: HTML, AJAX, JSON](responses-multiformat.md) | Multiformat: same action → full page, XHR fragment, or JSON; custom formats via `Response::registerFormat()`. |
| 8 | [Error handling](error-handling.md) | `BreakExecution`, `dispatchError`, exception logging, Monolog/Sentry integration. |

### Part B – Data and domain

| # | Guide | What you get |
|---|--------|----------------|
| 9 | [Database connections](database.md) | `dbconns.php`, `conn_ident`, multi-DB control. |
| 10 | [Models and records](models-and-records.md) | Model vs Record, Solr pointer, escape hatches. |
| 11 | [Records: behaviours & Solr](record-behaviours-solr.md) | Yii-style behaviours, `Record\Solr`, lifecycle (deeper). |
| 12 | [InfoHash](info-hash.md) | Id-keyed collections, secondary index, compose narrow SQL instead of wide joins. |

### Part C – Forms, assets, outbound HTTP

| # | Guide | What you get |
|---|--------|----------------|
| 13 | [Forms and validation](forms-and-validation.md) | Form object, elements, validators, filters. |
| 14 | [CSRF](csrf.md) | Component, form element, validator, non-form usage. |
| 15 | [Assets and JsOut](assets-and-jsout.md) | Named bundles, placeholders, JS pipeline. |
| 16 | [API client component](api-client-component.md) | Guzzle façade, remote APIs, optional form error mapping. |

### Part D – Meta and application patterns

| Guide | What you get |
|-------|--------------|
| [Flavors: one codebase, many environments](flavors-multi-app.md) | Not core UFW - env + `flavor`, multi-domain / multi-tenant apps (patterns from real projects). |
| [Config reference](config-reference.md) | Every config key the framework reads - lookup table. |
| [Security](security.md) | Overview + deployment checklist; links to CSRF and OWASP audit. |
| [Evaluation FAQ](evaluation-faq.md) | Fit for your project? TL;DR, poor/strong fit, FAQ by topic. |
| [Framework analysis](framework-analysis.md) | Strengths, risks, tradeoffs, recommendations - read after you know the flow. |
| [OWASP Top 10:2025 audit](security-owasp-audit.md) | Security categories vs UFW - checklist, not a pentest. |

---

## Index (all guides A–Z)

| Guide | Topic |
|-------|--------|
| [API client component](api-client-component.md) | Outbound HTTP |
| [Application bootstrap and registry](bootstrap-and-registry.md) | Bootstrap, Registry, singletons |
| [Assets and JsOut](assets-and-jsout.md) | Asset bundles, Placeholder, JsOut (concat/minify) |
| [Config reference](config-reference.md) | Every config key the framework reads |
| [Controllers](controllers.md) | Dispatch lifecycle |
| [CSRF](csrf.md) | Component, element, validator |
| [Database connections](database.md) | MySQL multi-connection, `Db` helpers |
| [Error handling](error-handling.md) | `BreakExecution`, logging, status mapping |
| [Evaluation FAQ](evaluation-faq.md) | Adoption/fit - TL;DR, poor/strong fit, FAQs |
| [Flavors (multi-app)](flavors-multi-app.md) | Env + flavor, one codebase |
| [Forms and validation](forms-and-validation.md) | Forms |
| [Framework analysis](framework-analysis.md) | Technical snapshot - strengths, risks, gaps |
| [Getting started](getting-started.md) | Zero-to-running-page quickstart |
| [Helpers](helpers.md) | Helper resolution |
| [InfoHash](info-hash.md) | Id-keyed collections, secondary index, compose narrow SQL |
| [Middleware](middleware.md) | Pipe, JSON, layout, auth |
| [Models and records](models-and-records.md) | Model / Record, relations (1–1 / 1–N / N–N) |
| [OWASP Top 10:2025 audit](security-owasp-audit.md) | OWASP 2025 risks vs framework defaults and app duties |
| [Records: behaviours & Solr](record-behaviours-solr.md) | Behaviours, Solr |
| [Responses: HTML, AJAX, JSON](responses-multiformat.md) | Multiformat responses; `Accept` / XHR; `registerFormat()` |
| [Routing](routing.md) | Routes - essentials |
| [Routing deep dive](routing-deep-dive.md) | Routing internals, custom routes, aliases |
| [Security](security.md) | Overview + deployment checklist |
| [Views, layouts, partials](views-layouts-partials.md) | Views |

---

*New here? Start with [Getting started](getting-started.md), then [Quick taste](#quick-taste-15-min). Ready to implement? Use [Full detail](#full-detail-all-guides).*

---

<p align="center">
  <img src="img/icon.png" alt="UFW" width="48" height="48" /><br />
  <sub><strong>μfw</strong> · micro framework</sub>
</p>
