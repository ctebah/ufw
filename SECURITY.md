# Security policy

## Supported versions

UFW is maintained from the `main` branch of [github.com/ctebah/ufw](https://github.com/ctebah/ufw). There is no formal LTS program; security fixes land on `main` and any active release line the maintainer announces in [CHANGELOG.md](CHANGELOG.md).

Minimum PHP version follows [composer.json](composer.json) (currently `>=7.4`; PHP 8.2+ recommended for new projects).

## Reporting a vulnerability

Please report suspected vulnerabilities privately first - do **not** open a public GitHub issue with exploit details.

- Preferred: open a private report via [GitHub security advisories](https://github.com/ctebah/ufw/security/advisories/new).
- Fallback: contact the author through the profile on [github.com/ctebah](https://github.com/ctebah).

When you report, please include:

- Affected UFW version (commit hash or release tag) and PHP version.
- Minimal reproduction or description of the attack path.
- Impact you observed (read/write/escalation/DoS, scope of data).
- Any suggested fix, if you have one.

You will receive acknowledgement within a reasonable timeframe. Coordinated disclosure is appreciated; once a fix lands, the advisory will be published with credit to the reporter unless you ask to remain anonymous.

## Scope

In scope: code under `src/` (framework core), dependencies pinned in [composer.json](composer.json) where the vulnerability exists in the way UFW uses them, and documented patterns in [docs/](docs/README.md) that would mislead a careful reader into an insecure implementation.

Out of scope: application-level mistakes in projects that *use* UFW (auth, session, TLS, secret handling, SQL assembly from raw input) - those are covered by per-project review. See [docs/security.md](docs/security.md) for the application-side checklist and [docs/security-owasp-audit.md](docs/security-owasp-audit.md) for the category-by-category mapping of what UFW provides vs. what remains the application's duty.

## Related

- [docs/security.md](docs/security.md) - overview and deployment checklist.
- [docs/security-owasp-audit.md](docs/security-owasp-audit.md) - OWASP Top 10:2025 audit against framework defaults.
- [docs/csrf.md](docs/csrf.md) - CSRF component, form element, and validator.
- [docs/error-handling.md](docs/error-handling.md) - exception logging and status code mapping.
