# Is UFW a good fit? - evaluation FAQ

← [Documentation index](README.md)

Goal: after one read of this page, you should be able to say either "worth a short spike" or "not my concept - I will skip it" - and in both cases stop guessing and save time.

How this pairs with other docs: [Framework analysis](framework-analysis.md) is a technical snapshot of the current tree (what is solid, what is risky). This page is adoption / fit (people, license, ecosystem, rough edges). Read either order; many teams skim this FAQ first, then the analysis before a spike.

---

<a id="at-a-glance"></a>

## At a glance (TL;DR)

| | |
|--|--|
| What it is | PHP MVC + middleware + server-rendered forms first; Laminas-flavored HTTP; regex routes. Smaller ecosystem than Laravel/Symfony - you read this documentation and the source. |
| Track record | 8+ years of production use since the first UFW versions - evolved in real projects, not a paper design. |
| License | [0BSD](../LICENSE) - commercial and private use, modify freely. |
| PHP / hosting | 7.4+ required; 8.2+ recommended. Normal PHP-FPM + web server; you own vhost and TLS. |
| Support | No implied SLA, corporate roadmap, or LTS - maintenance follows maintainers and adopters. |
| Differentiator | One controller action can serve HTML (full page or AJAX fragment) and JSON from the same logic - see [responses-multiformat.md](responses-multiformat.md). |
| Code reality check | [Framework analysis](framework-analysis.md) - strengths, risks, and honest gaps in the current tree. |

If two or more rows in the "poor fit" list below sound like you, stop here. If several "strong fit" bullets match, a short spike (skeleton + one feature) is a reasonable next step - see [Documentation index](README.md).

---

<a id="how-to-use"></a>

## How to use this

Read "At a glance" above, then "Poor fit / Strong fit" (next). Use the FAQ sections below as a lookup table - order is priority for newcomers (blockers and differentiators first), not alphabetical.

Jump: [At a glance](#at-a-glance) · [Poor / Strong fit](#poor-strong-fit) · [Blockers & policy](#faq-blockers) · [People & ecosystem](#faq-people) · [Product & technical](#faq-product) · [Migration & long term](#faq-migration) · [Related reading](#related-reading)

---

<a id="poor-strong-fit"></a>

## Poor fit / Strong fit

### When is UFW a poor fit?

Rough signals:

- You need the **maximum** community packages and hiring pool of one ecosystem name (Laravel/Symfony-only shops).
- You want zero globals/singletons and full dependency injection everywhere. UFW uses `Registry` and patterns that favor pragmatism over DI purity.
- You insist on PDO prepared statements and no escape-based DB helpers. The bundled `Db` layer is MySQLi-oriented and convenience-first; PDO can sit beside it, but it is not the default story.
- You expect long-term framework churn driven by a huge core team. UFW is smaller; releases follow maintainer cadence and you should plan to track upstream and apply upgrades on your schedule.

### When is UFW a strong fit?

Rough signals:

- You want routing, controllers, middleware, forms, and DB helpers in one consistent style without adopting a mega-framework.
- Server-rendered forms matter: validation, rendering, and CSRF building blocks are first-class (you still own policy and auth wiring).
- You care about shipping HTML without a SPA: asset bundles, JS placeholders, and optional JsOut output fit multi-page apps that stay fast with disciplined deploys.
- You value readable, end-to-end documentation of how pieces connect - not only class lists.
- You use AI-assisted coding **with review**, not blind acceptance: an explicit tree, [Framework analysis](framework-analysis.md), and [Documentation index](README.md) reduce bad guesses; the skeleton app (`ufw-extras`) is the reference to open beside the prompt.
- You are comfortable subclassing and replacing what does not fit - the docs say so repeatedly.

---

<a id="faq-blockers"></a>

## FAQ - blockers & policy

### Can I use it commercially? What is the license?

Yes. The project is under [0BSD (Zero-Clause BSD)](../LICENSE). In plain terms: use, copy, modify, and distribute for any purpose, including closed-source and commercial products, without copyleft obligations, fees, or extra conditions from the author. Ship it inside a product, rebrand as needed - the license is meant to stay out of your way.

Attribution is not legally required by 0BSD, but crediting the original author (copyright line in `LICENSE`) is appreciated if you publish something derived from or visibly built on UFW.

Bottom line: maximum permission, minimum strings - no GPL-style "you must open your code" surprise.

### What PHP version and hosting does it need?

PHP 7.4+ is required (`composer.json` declares `>=7.4`). PHP 8.2+ is recommended for new projects (performance, language features, upstream support). Typical deployment is PHP-FPM (or mod_php) + a web server - the same class of hosting as other Composer-based PHP apps. You still configure vhost, HTTPS, and PHP version yourself.

Bottom line: not "shared-hosting one-click"; not exotic either.

### Is there commercial support, a roadmap, or guaranteed long-term maintenance?

Assume none unless you contract someone. Like many OSS PHP libraries, maintenance cadence follows maintainers and adopters. There is no implied SLA, LTS promise, or corporate backing described here.

Bottom line: fit if you can carry upgrades and security review in your own process (or contract someone to do it).

---

<a id="faq-people"></a>

## FAQ - people & ecosystem

### Will I find tutorials, Stack Overflow answers, and video courses?

Fewer than for Laravel or Symfony. UFW is a smaller ecosystem: you lean on this documentation, the source, and your own debugging. That is a real cost if your team learns best from endless third-party content.

Bottom line: worth trying only if you are comfortable reading framework docs and code when Google is thin.

### Is hiring / onboarding developers easy?

Easier if candidates know classic PHP MVC and middleware concepts than if you require "UFW on the CV." The ideas transfer from other stacks; the names and conventions are UFW-specific and take a short ramp-up.

Bottom line: optimize for strong PHP engineers, not framework-brand recognition.

### Is it easy to pick up and use day to day?

Moderately. UFW follows familiar PHP MVC ideas: routes → controllers → views, plus helpers, middleware, and a form stack that feels closer to classic Zend/Laminas than to a minimal router-only library. If you already know that world, concepts map quickly. If you expect Laravel-style ergonomics (Artisan, Eloquent-first docs, huge ecosystem), you will feel more friction: fewer batteries included, more conventions you read once in the docs.

Bottom line: productive once the mental model clicks; not optimized for "zero reading, copy from Stack Overflow for everything."

### Does the codebase work well with AI coding assistants (Cursor, Claude, Copilot, …)?

Usually yes - because the framework favors explicit structure over magic. Controllers, views, forms, and routes are ordinary PHP at predictable paths; [Framework analysis](framework-analysis.md) and the guides in [Documentation index](README.md) explain how pieces connect. Assistants (and humans) do better when they can grep the tree and ground answers in one doc instead of reverse-engineering a huge convention stack.

The skeleton app in the companion `ufw-extras` repository is a vertical slice - routes, layout, forms, assets - you can point a model at as reference. It is not a one-click generator; a few hours to a running demo is a realistic bar for a first pass (human + AI), not minutes.

This is **not** a match for "vibe coding" - accepting whatever the model outputs without reading diffs, checking routes/forms against UFW conventions, or knowing roughly what the tool changed. It is a strong match when you treat AI as fast implementation, not a substitute for judgment: understand what the LLM did (or verify before merge), keep the docs and skeleton in reach, review diffs.

Bottom line: guided generation with review works well; prompt-only, eyes-closed generation does not.

---

<a id="faq-product"></a>

## FAQ - product & technical fit

### Can I build JSON APIs or only HTML?

Both - and you do not duplicate business logic. A typical UFW shape: think of each action as an API (input → validation → domain work → result), then add HTML only where you need a human UI (a layout plus a view). The same code path answers browsers (HTML, optionally full layout or AJAX fragment) and API clients (JSON via `Accept` / format negotiation - see [responses-multiformat.md](responses-multiformat.md)). You do not maintain an "API version" and a "web version" of the same feature side by side.

UFW is not "API-only first" in marketing terms - server-rendered HTML is first-class - but architecturally you can keep one flow and layer presentation. For a pure public API with OpenAPI-first tooling and huge ecosystems, larger frameworks may still offer more ceremony out of the box; this stack shines when the product is naturally hybrid (admin, internal tools, sites that also expose JSON).

Bottom line: strong when you want one implementation, two or three transports - HTML, JSON, XHR fragments - without copying controllers.

### What about security - CSRF, auth, sessions?

UFW ships building blocks (CSRF component, form element, validator - see [csrf.md](csrf.md)), HTTP header sanitization, and hooks for middleware-based auth. It does not ship a complete identity product (no turnkey OAuth2 server, no user table migration). Production security (HTTPS, cookie flags, session hardening, threat model) remains your responsibility, as with most PHP frameworks at this scale. See [security.md](security.md) for the deployment checklist.

Bottom line: fits teams comfortable owning auth and deployment policy.

### What about performance and traffic at scale?

UFW can serve real production apps, but it is not benchmark-marketed. Routing is a linear scan over route patterns (fine for modest tables; see [Framework analysis](framework-analysis.md)). Heavy scale usually means your caching, DB design, and infra - not a magic core.

Where the defaults help is perceived page speed when you use the stack as intended:

- Asset bundles ([assets-and-jsout.md](assets-and-jsout.md)): named CSS/JS manifests, optional minified bundles in production (`serve-minified`), fewer HTTP requests than ad-hoc includes per page.
- Sensible ordering: stylesheets in the head; scripts at body-bottom with `defer` where configured - less render-blocking than script tags scattered mid-page.
- Placeholder pipeline: page and form code append to `jsReady` / `jsInline` (and related) placeholders; `JsOut` concatenates and minifies that output into cacheable files under a configured web path, so repeat visitors hit stable URLs with long-lived browser cache.
- The rest is ops: gzip/brotli, CDN, TTLs, cache-busting on deploy. Not automatic "make it fast" buttons - but with consistent deploy discipline, static output and fewer round-trips add up.

Raw TTFB to a public URL is a noisy datapoint. For user-perceived speed, prefer lab or field metrics (LCP, INP) and profile your own deployment. Overall page speed still depends on content weight, database, and hosting.

Bottom line: judge with profiling under your load; expect real gains from bundle discipline + placeholders + static caching, not from hype.

### Is it "lightweight" or "heavy"?

In the middle. The Composer graph includes Guzzle, Monolog, Laminas Diactoros/Stratigility, Negotiation - not a 50-line micro-router, but also not a full "kitchen sink" framework. You pay dependency weight for real HTTP and middleware interoperability.

Bottom line: if you need the smallest possible dependency footprint, look elsewhere. If you want PSR-7-flavored HTTP without adopting Symfony wholesale, the tradeoff can be reasonable.

### How extensible is it? Can I replace core pieces?

Yes - that is an explicit design goal. Helpers resolve app-first (`\Helper\*` over `\Ufw\Helper\*`). You subclass `Controller\Base`, swap records, use plain PHP where forms are overkill, and wire auth in middleware. The framework defaults are shortcuts, not contracts you cannot escape.

Bottom line: good fit if you want a spine (dispatch, HTTP, forms, DB helpers) without being locked into one vendor's full stack.

### Does UFW force a particular stack or workflow?

Opinionated about structure, not about your whole universe.

- Forced-ish: PHP, the Application → Router → Controller flow, Laminas-based HTTP/middleware plumbing, regex routes, server-rendered forms as a first-class story.
- Not forced: front-end framework (bring React/Vue or none), ORM (records are thin; you can use something else), queue/cache layers, hosting (typical PHP-FPM + web server is enough).

Bottom line: it does not impose a SPA toolchain or a single ORM. It does impose its routing and dispatch model - by design.

### Is this the old "framework on top of framework" idea?

Partly in spirit, not in architecture. Years ago it was common to nest large stacks (heavy module layers on top of another full framework). Today PHP teams more often compose libraries via Composer than run two competing application kernels in one process.

UFW's intended pattern is inherit and specialize - `Controller\Base`, `Form\Base`, helpers - customize what you need, replace what you do not. That is "use the framework as a base," not "install Laravel inside Symfony inside…" Nesting entire frameworks usually duplicates routing, DI containers, and bootstraps and is hard to reason about.

Bottom line: prefer UFW + targeted packages over two full stacks fighting for the request.

### Can I use Symfony, Laravel, or Illuminate components alongside UFW?

Yes - in practice, libraries, not whole stacks. Anything Composer-installable that behaves as a library (single responsibility, no assumption that it owns the HTTP request lifecycle) can work: Symfony Translation, String, Validator (with adapters), parts of Illuminate if you instantiate and wire them yourself - the same way many apps mix Laminas pieces UFW already depends on.

i18n is a typical case: wire Symfony Translation (or another catalog) into your bootstrap and expose it through the `translator` callable the [helpers](helpers.md) docs describe - UFW does not mandate *which* implementation sits behind that hook.

Caveats: avoid bootstrapping half of Laravel's kernel or Symfony's HttpKernel expecting them to own dispatch - you will fight duplicate routing, middleware, and global state. Prefer one application layer (UFW) and leaf libraries underneath.

Bottom line: components yes; framework-inside-framework usually no.

---

<a id="faq-migration"></a>

## FAQ - migration & long term

### Greenfield only, or can I migrate an existing app?

Greenfield or partial migration both appear in real life. UFW does not ship automated migrators from Laravel/Symfony. You port routes, controllers, and views incrementally or in one pass - same as most framework moves.

Bottom line: feasible with planning; effort is project-sized, not a one-click switch.

### What about internationalization (i18n) and localization?

Hooks exist (a `translator` callable in the registry - see [helpers](helpers.md) / `Translate`). You still provide message catalogs, locale selection, and date/number formatting as your product requires.

Bottom line: not a full i18n suite out of the box; extensible if you invest.

### Am I locked in? Can I peel UFW off later?

No framework is zero-cost to remove. UFW's surface is controllers, helpers, forms, and `Db` helpers - replacing it means rewriting those boundaries. Because the stack is moderate-sized (not every line of your app), incremental extraction is imaginable for disciplined teams.

Bottom line: treat adoption as a multi-year bet, like any framework - with smaller community escape hatches than mega-frameworks.

### Tests, static analysis, long-term maintenance?

The framework repository has historically prioritized shipping a coherent vertical slice over a large automated test matrix. You should plan your own tests and tools (PHPUnit, PHPStan/Psalm) in your project. [Framework analysis](framework-analysis.md) discusses gaps and recommendations honestly.

Bottom line: good if you accept owning quality gates in application code; weaker if you require framework-level CI as your primary safety net.

---

<a id="related-reading"></a>

## Related reading

| Document | Role |
|----------|------|
| [Framework analysis](framework-analysis.md) | Honest technical snapshot: strengths, risks, tradeoffs (current tree). |
| [Documentation index](README.md) | How to learn UFW: ordered guides and principles. |
| [Project README](../README.md) | What UFW claims to be at a glance. |

---

← [Documentation index](README.md)
