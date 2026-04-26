# CSRF

← [Documentation index](README.md)

UFW ships CSRF (Cross-Site Request Forgery) protection as a small set of primitives you opt into per state-changing request. Nothing is automatic: every form or manual POST must attach a token and the receiving action must validate it.

The pieces live in three places:

| Class | Role |
|-------|------|
| [`Ufw\Component\Csrf`](../src/Component/Csrf.php) | Session-backed synchronizer tokens. Three static methods: `token()`, `validate()`, `rotate()`. |
| `Ufw\Form\Element\Csrf` | Hidden form element that reads the token and auto-attaches the validator. Extends `Hidden`; `ignore = true` so its value is excluded from `getValues()`. |
| `Ufw\Form\Validator\Csrf` | Validator bound to the element; delegates to the component's `validate()`. |

## Component (direct use)

`Ufw\Component\Csrf` is the core primitive. Use it directly when you render a page without `Ufw\Form\Base` (standalone login template, hand-written forms, non-form AJAX calls that still need protection).

```php
use Ufw\Component\Csrf;

$token = Csrf::token('contact');       // creates/reads session-scoped token
Csrf::validate('contact', $submitted); // bool; hash_equals comparison
Csrf::rotate('contact');               // issue a fresh token after a successful action
```

Internals (see [`src/Component/Csrf.php`](../src/Component/Csrf.php)):

- Tokens are 64 hex chars (`bin2hex(random_bytes(32))`).
- Comparison uses `hash_equals`.
- `session_start()` is called automatically if the session is not already open.
- Storage key is `SESSION_PREFIX . $purpose` (default prefix `ufw.csrf.`); subclass and override `SESSION_PREFIX` if you need a different namespace.
- Default HTML input name is `Csrf::DEFAULT_FIELD_NAME` (`csrf_token`). Read via `Csrf::getDefaultFieldName()`.

### Purposes

The `$purpose` string names an independent token. Use distinct purposes per sensitive flow (`login`, `contact`, `admin_delete_user`, …) so a leaked token from one page cannot be replayed against another. `'default'` is fine for low-sensitivity forms but not recommended for admin actions.

### Non-form page example

```php
// In the template
<?php $token = \Ufw\Component\Csrf::token('login'); ?>
<form method="post" action="/login">
    <input type="hidden"
           name="<?= htmlspecialchars(\Ufw\Component\Csrf::DEFAULT_FIELD_NAME, ENT_QUOTES, 'UTF-8') ?>"
           value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
    <!-- username/password fields -->
    <button type="submit">Log in</button>
</form>
```

In the controller:

```php
$submitted = $this->getRequest()->getPost(\Ufw\Component\Csrf::DEFAULT_FIELD_NAME);
if (!\Ufw\Component\Csrf::validate('login', $submitted)) {
    throw new \Ufw\Exception\BreakExecution('Forbidden', 403);
}

// ... perform login ...

\Ufw\Component\Csrf::rotate('login'); // old token cannot be replayed
```

## Form element (`Ufw\Form\Base` flow)

For forms built on `Ufw\Form\Base`, add the element once in your form's `init()`:

```php
namespace Form;

class Contact extends \Ufw\Form\Base
{
    public function init()
    {
        // other elements ...

        $this->addElement(
            new \Ufw\Form\Element\Csrf(
                \Ufw\Component\Csrf::DEFAULT_FIELD_NAME,
                ['purpose' => 'contact']
            )
        );
    }
}
```

The element (see [`src/Form/Element/Csrf.php`](../src/Form/Element/Csrf.php)):

- Renders as a single hidden input through the partial `form/csrf.php` (no label row).
- Sets its value to the token in `init()`.
- Auto-attaches `Ufw\Form\Validator\Csrf` tied to the same purpose.
- Is excluded from `getValues()` (like submit buttons) so your record/DTO never sees the token.

### After a successful submission

Call `rotate()` once the state change succeeds, so a refresh-replay or leaked token cannot be used again:

```php
if ($form->isValid($this->getRequest()->getPost())) {
    // ... persist, send mail, etc.

    \Ufw\Component\Csrf::rotate('contact');
    $this->redirect('/thanks');
}
```

## Overriding the component

The element reads its backing class from option `csrfComponent` (class-string) if set, falling back to `Ufw\Component\Csrf`. The class must provide the same static `token()` / `validate()` API.

```php
$this->addElement(
    new \Ufw\Form\Element\Csrf(
        \Ufw\Component\Csrf::DEFAULT_FIELD_NAME,
        ['purpose' => 'contact', 'csrfComponent' => \App\Security\AppCsrf::class]
    )
);
```

Alternatively subclass `Ufw\Form\Element\Csrf` and override `getCsrfComponentClass()`.

Reasons to override: a different session namespace, integration with a request-bound token store (e.g. double-submit cookie), or a custom class that logs validation failures before returning `false`.

## What UFW does *not* do automatically

- There is no middleware that auto-validates every POST. Forgetting the element on a form or the `validate()` call in a controller is a **design/process failure**, not something the framework can mask.
- There is no cross-origin CSRF header check. Same-site cookies and origin enforcement at the web-server or session layer are still your call.
- There is no sliding expiry. `rotate()` when the action succeeds; otherwise the token lives as long as the session.

## See also

- [forms-and-validation.md](forms-and-validation.md) - full form stack.
- [helpers.md](helpers.md) - how the element is rendered through form-render helpers.
- [security.md](security.md) - broader deployment security checklist.
- [security-owasp-audit.md](security-owasp-audit.md) - where CSRF fits in the OWASP Top 10:2025 mapping.

---

**Next:** [Assets and JsOut](assets-and-jsout.md) - CSS/JS bundles and optional JS pipeline.
