# Forms and validation

← [Documentation index](README.md)

## Form class

Subclass **`Ufw\Form\Base`**. The form owns:

- **Attributes** – HTML attributes (`method`, `action`, `id`, `class`, …).
- **Elements** – inputs, selects, multi-controls (`Ufw\Form\Element\*`).
- **Validators** – attached per element or form (`Ufw\Form\Validator\*`).
- **Filters** – normalize input before validation (`Ufw\Form\Filter\*`).

Traits **`Attribs`**, **`Options`**, **`Elements`**, **`Translate`** provide the fluent-ish API.

## Typical flow

1. Instantiate the form (optionally with options).
2. On POST, call **`isValid($data)`** or populate from request (`setDefaults` / `populate` patterns depending on your subclass).
3. Validators run; errors attach to elements.
4. Render with Bootstrap form helpers under `Ufw` views and set **`form.helper`** in config:
   - `bootstrap3` → `src/views/partials/bootstrap3/` (includes Material Icons markers in checkbox/radio partials; load Material Icons font/assets),
   - `bootstrap4` → `src/views/partials/bootstrap4/` (same Material Icons note),
   - `bootstrap3plain` / `bootstrap4plain` → plain checkbox/radio partials with no Material Icons dependency,
   - `bootstrap5` → `src/views/partials/bootstrap5/` (Bootstrap 5 markup: `mb-3`, `form-check`, `is-invalid` / `invalid-feedback`, no Glyphicons dependency).
   Load matching Bootstrap CSS/JS (and jQuery if you use stock Menu/reCAPTCHA partials) in your layout.

## Validators

Common validators include **Required**, **Email**, **StringLength**, **InArray**, **RegEx**, **Identical** (confirm password), **FileUpload**, **ReCaptcha** – each extends `Ufw\Form\Validator\Base`.

## Filters

Examples: **Trim** – strip whitespace before validation.

## Controllers

Controllers usually **instantiate the form**, pass **`$request->getPost()`** (or merged params), and branch on **`isValid()`**. Use **`FormErrorsToFlashMessenger`** (or similar helper) to surface errors on redirect.

After a successful **`isValid()`**, map values into your **`Record`** (and relation hooks if any) or call **model** methods that persist - see **[models-and-records.md](models-and-records.md)**.

## Design note

This stack is optimized for **server-rendered HTML** and **progressive enhancement**, not for SPA-only JSON APIs. For JSON APIs, validate payloads with your own rules or a thin DTO layer; the full form stack is optional.

## Future work (non-binding)

- **Form + element schema as JSON** - `JsonSerializable` (or a dedicated serializer) on `Ufw\Form\Base` and, recursively, elements. The goal: the same form object that drives server-side validation and optional HTML rendering should also expose a stable JSON description (form-level options, each element's type/options/labels, validation rules, current values, errors after validation). A mobile app or SPA could fetch that payload and render its own UI, while submission and `isValid()` stay on the server - no parallel hand-written DTOs duplicating field lists and rules. Not implemented yet.

---

**Next:** [CSRF](csrf.md) - component, form element, validator, non-form usage.
