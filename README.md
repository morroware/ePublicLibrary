# ePublicLibrary

A self-hosted ePub library and reader. Public browsing, optional accounts,
admin upload, modern reader UI. Vanilla PHP + MySQL — no framework, no build
step, runs on shared cPanel hosts.

## Features

- **Public library**: anyone can browse the catalog and read books.
- **Optional accounts**: sign in to sync reading progress, bookmarks, and
  shelves across devices.
- **Admin upload**: drag-drop EPUB upload with metadata extraction and cover
  thumbnails generated automatically.
- **Modern reader**: themes (light / dark / sepia), font controls, bookmarks,
  table of contents, keyboard / touch / swipe navigation.
- **Hardened**: Argon2id passwords, CSRF protection, rate limiting,
  per-request CSP nonces, audit log, defense-in-depth `.htaccess`.
- **Subdirectory-friendly**: install at `/` or `/library/` or anywhere —
  every URL is computed from a single auto-detected `base_url`.

## Requirements

- PHP 8.0+ with extensions: `pdo_mysql`, `zip`, `gd`, `dom`, `mbstring`, `fileinfo`
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` (recommended) or any web server that honors `.htaccess` deny rules
- ~10 MB disk space, plus your books

No Composer. No Node. No build pipeline.

## Quick start

1. Upload this directory to your web root (or a subdirectory like `library/`).
2. Create a MySQL database + user (cPanel → *MySQL® Databases*).
3. Open `https://your-site.com/setup.php` in a browser and walk through the
   wizard: requirements check → database connection → site name → admin
   account → install.
4. Done. Visit your library. Upload books via *Admin → Upload*.

See [INSTALL.md](INSTALL.md) for the full walkthrough including cPanel
specifics.

## Architecture at a glance

```
public files (web-served)
├── index.php, book.php, read.php, login.php, ...
├── api/        — JSON endpoints (download, search, progress, bookmarks, ...)
├── admin/      — admin tools (upload, books, users, audit, migrate, ...)
├── assets/     — CSS, JS, fonts, covers, vendored libs
└── setup.php   — one-time install wizard (self-disables after run)

private (.htaccess-denied)
├── includes/   — PHP code: bootstrap, helpers, classes
│   └── classes/    — light OO layer (repositories, services, parsers)
├── views/      — PHP templates (layouts, partials, pages, errors)
├── storage/    — books, uploads, logs, cache (writable)
└── database/   — migrations
```

Every page entry starts with the same one-liner:

```php
define('APP_BOOTED', true);
require __DIR__ . '/includes/bootstrap.php';
```

`bootstrap.php` loads config, opens the DB, starts the session, sends
security headers, resolves the current user. Pages then call into the small
class library (`BookRepository`, `EpubParser`, etc.) and render views via
`render('library/index', $vars)`.

## Where things live

- **Books on disk**: `storage/books/{shard}/{uuid}.epub` (shard = first 2
  chars of UUID, keeps directories small).
- **Covers**: `assets/covers/{uuid}.jpg` (publicly served — no PHP overhead
  per image).
- **Logs**: `storage/logs/{app,auth,error}.log` (daily, rotated by the host).
- **Config**: `includes/config.php` (gitignored; written by `setup.php`).

## Project status

This is a major refactor of an earlier proof-of-concept. The codebase is
phased:

- **Phase 1 (this release)**: foundation — DB-backed library, real
  authentication, security hardening, dropped Tailwind CDN, accessibility
  baseline, setup wizard.
- **Phase 2**: collections, "Continue Reading" rail, book detail page,
  advanced search.
- **Phase 3**: highlights & annotations, in-book search, dictionary popup,
  text-to-speech.
- **Phase 4**: reading stats, immersive mode, offline support, reviews.

See [CHANGELOG.md](CHANGELOG.md) for what's in this release.

## Security

See [SECURITY.md](SECURITY.md) for the threat model and disclosure process.
