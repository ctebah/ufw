# Flavors: one codebase, many environments

← [Documentation index](README.md)

**This is not a UFW core feature.** It is a **common application-level pattern** on top of UFW: the same PHP tree serves **different products, domains, or audiences** by swapping **configuration** (and sometimes routes, menus, or controllers) instead of maintaining separate forks.

---

## How it fits together

| Idea | Role |
|------|------|
| **`APPLICATION_ENV`** (or similar constant) | Bootstrap picks which **`app.<env>.php`** (or chain of includes) becomes the base config - e.g. **development** vs **production**, or extra env names for staging. |
| **`env` in config** | Optional string **`$application->getConfig()['env']`** - **`Ufw\Envs::runtimeLabel()`** reads it (same layered resolution as **`Paths`**). **Menu** item **`env`** filters use that label. Set in merged config or via **`Envs::setRuntimeEnvLabel()`** / Registry **`env`** / **`APPLICATION_ENV`**. |
| **`flavor` in config** | A string or enum in **`$application->getConfig()['flavor']`** for **feature toggles**, branding, navigation, RBAC - “which variant of this app is running?”. |
| **Shared `routes.php`** | Often one **`dispatch.routes`** include; behavior differs through **defaults**, **constraints**, or supplemental route files merged per flavor if you need it. |
| **Per-flavor config fragments** | Include files under e.g. **`config/flavors/…`** merged into the main array - scales when many tenants share one **codebase**. |

UFW still exposes **one** `Application`, **one** router, **one** dispatch pipeline; **your** layer decides how many **logical apps** sit behind it.

---

## Examples (abstracted from production setups)

### Example: two main “faces” (e.g. internal vs public)

Different **`flavor`** values and different **top-level config** files selected by **`APPLICATION_ENV`** (including development-specific variants). **Routes** often live in a **single** `routes.php` included after the switch; divergence is mostly **config-driven** plus code that reads **`flavor`** (templates, menus, feature gates).

### Example: many flavors, env-based composition

A **PHP `enum`** (or equivalent) names **many** institutional or tenant-style **flavors**. **`APPLICATION_ENV`** switches **development** vs **production** shells; **production**-style config then composes **per-flavor** data from a **flavors/** tree (labels, feature flags, integrations). Menus and policies use **`$config['flavor']`** so the **same codebase** serves **different domains and roles** without duplicating the framework.

### Config-heavy workflows

If you like **explicit configuration**, this pattern shines: **env** selects files; **flavor** selects behavior inside those files. Nothing here is prescribed by UFW - it is how large UFW apps **organize** multi-tenant or multi-product delivery.

---

## Takeaway

**UFW does not ship “flavors”.** Your app does. Prefer **config + targeted conditionals** over copy-paste repositories when the stack is the same.

---

**See also:** [Routing](routing.md) · [Documentation index](README.md)
