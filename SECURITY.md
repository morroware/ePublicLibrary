# Security

## Threat model

ePublicLibrary is designed for self-hosting on shared hosts where:

- Anyone with the URL can browse the library and read books (this is the point).
- Account registration is open — anyone can create a reader account.
- A small number of administrators upload and manage books.
- The host's file system is shared with other tenants; defense in depth matters.

Assumed adversaries:

- Random internet visitors trying common credentials, scraping the catalog,
  or probing for misconfigured endpoints.
- Bots crawling the open internet for outdated PHP apps.
- Mistakes by the admin (committing secrets, weak passwords).

Out of scope (for now):

- Determined attackers with physical or hypervisor access to the server.
- DoS resistant beyond per-IP rate limits.
- Cross-site cooperative attacks involving the user's other browser tabs.

## Defenses

| Concern | Mitigation |
|---|---|
| **SQL injection** | All queries use prepared statements via PDO. Repositories are the only place that touch the DB. |
| **XSS** | Output is escaped with `e()` (htmlspecialchars w/ ENT_QUOTES|ENT_SUBSTITUTE). EPUB content is sandboxed in `epub.js` iframes. |
| **CSRF** | Per-session token rotated on login, embedded in all forms and sent in the `X-CSRF-Token` header for XHR. `hash_equals` verification. |
| **Session theft** | Cookies are `HttpOnly; Secure (HTTPS); SameSite=Lax`. Session ID is regenerated on login/logout. Optional remember-me uses split-token rotation. |
| **Password attacks** | Argon2id hashing. NIST 800-63B-aligned rules (12+ chars, plus a common-password check on Phase 4). Failed logins take constant time. |
| **Brute force / credential stuffing** | Per-IP and per-username sliding-window throttle in the `rate_limits` table. Account lockout after 5 failed attempts. |
| **Path traversal** | `BookFileStorage::resolveForBook()` uses `realpath()` and asserts the path stays inside the configured books root. |
| **Upload abuse** | Multi-layer validation: extension, finfo MIME, magic bytes, `ZipArchive::open()`, mimetype-entry check, `META-INF/container.xml` parses, file-size cap, SHA-256 dedup. Files are quarantined in `storage/uploads/tmp/` before being atomically moved. |
| **Direct file access** | `.htaccess` denies `includes/`, `views/`, `storage/`, `database/`, and `config.php`. EPUB downloads go through `api/download.php` (auth + rate limit + audit log). |
| **Clickjacking** | `X-Frame-Options: DENY` and `frame-ancestors 'none'` in CSP. |
| **MIME sniffing** | `X-Content-Type-Options: nosniff` everywhere. |
| **Information disclosure** | `display_errors=0` in production. Errors logged to `storage/logs/error.log`; generic 500 page on failure. |
| **Audit trail** | `audit_log` captures logins, role changes, uploads, deletions, suspicious remember-me mismatches. |

## Content-Security-Policy

The default policy is:

```
default-src 'self';
script-src 'self' 'nonce-{N}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;
style-src 'self' 'nonce-{N}' 'unsafe-inline';
img-src 'self' data: blob:;
font-src 'self' data:;
connect-src 'self';
worker-src 'self' blob:;
frame-src 'self' blob:;
object-src 'none';
base-uri 'self';
form-action 'self';
frame-ancestors 'none';
```

The two CDN hosts in `script-src` are only used as a fallback when
`assets/vendor/` is empty. After vendoring the libraries locally (see
[`assets/vendor/README.md`](assets/vendor/README.md)), you can remove the
CDN hosts from the `$cdnHosts` constant in `includes/security.php` to
fully eliminate third-party origins.

`'unsafe-inline'` remains in `style-src` to support a small amount of
runtime style mutation by `epub.js` for theme application. The nonce
mechanism is also in place; when an upstream `epub.js` exposes a hook for
nonces, that can replace `'unsafe-inline'`.

## Post-install hardening

These are quick wins after the installer finishes — they aren't enforced by
code, so it's on the operator to do them:

1. **Delete `setup.php`.** The installer self-locks once it finishes (re-running
   it shows a "setup already complete" page), but deleting the file removes
   the attack surface entirely. The "setup complete" page in the wizard now
   tells you to do this.
2. **Schedule the maintenance purge.** `auth_tokens` and `rate_limits` are
   pruned on demand from **Admin → Maintenance**. For long-running installs
   add a weekly cron entry — see `INSTALL.md`.
3. **Vendor `epub.js` and `jszip` locally** (see `assets/vendor/README.md`)
   then strip the two CDN hosts from `$cdnHosts` in `includes/security.php`
   to eliminate cross-origin script sources.

## What's NOT yet protected (roadmap)

- **Two-factor authentication** — planned for a future release.
- **Per-IP request quotas beyond login/register/download** — coarse-grained
  rate limiting only.

## Reporting a vulnerability

If you discover a security issue, please contact the maintainer privately
rather than opening a public issue. Provide:

1. A description of the vulnerability.
2. Steps to reproduce.
3. The affected version (see `app_version` in `includes/config.php`).

We aim to acknowledge reports within 7 days.
