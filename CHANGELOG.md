# Changelog

## 1.0.0 — Foundation release

This release rebuilds the project from the v7.x BookShelf proof-of-concept
into a deployable platform. The legacy script files are removed; the
application now follows a structured layout with proper authentication and
a MySQL-backed data layer.

### Added

- **MySQL data layer.** 13 tables for users, books, tags, reading progress,
  bookmarks, highlights, collections, reviews, audit log, sessions, auth
  tokens, and rate-limit buckets. Full-text search on `books`.
- **Setup wizard** (`setup.php`) — multi-step browser install: requirements
  check, DB connection test, site config (auto-detects subdirectory installs),
  admin account creation, schema migrations, sentinel lock.
- **Real authentication.** Argon2id password hashing, DB-backed sessions,
  CSRF protection, rate limiting (per-IP + per-username), remember-me with
  split-token rotation, audit logging.
- **Admin panel** under `/admin/`:
  - Dashboard with library stats
  - EPUB upload with full validation pipeline (extension + MIME + magic
    bytes + ZIP + mimetype entry + container.xml + size + dedup)
  - Book listing with edit / delete
  - User management (role + status + admin-triggered password reset)
  - Audit log viewer
  - Migration runner with drift detection
  - Thumbnail batch rebuild
  - Legacy `books/` directory importer
- **Modern reader** at `/read.php`:
  - epub.js viewer with sepia / light / dark / auto themes
  - Font family, size, line height, margin controls
  - Bookmarks, table of contents, reading progress
  - Click hotspots + keyboard + swipe navigation
  - Server-synced state for logged-in users; localStorage fallback for guests
- **Accessibility baseline**:
  - ARIA roles on book cards, autocomplete combobox, reader panels
  - Keyboard navigation throughout (Tab cycling, Escape closes, focus return)
  - Skip-to-content link, `prefers-reduced-motion` respect
  - Visible focus rings
- **Subdirectory-friendly URLs**: no hardcoded leading slashes anywhere.
  Every link/asset goes through `url()` / `asset()` helpers that prepend
  the auto-detected base URL.
- **Security headers**: per-request CSP nonce, HSTS, `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`.
- **Path-traversal hardening**: `realpath()`-based checks replace the
  legacy `strpos('..')` guards.
- **EPUB metadata extraction** via `DOMDocument` + XPath instead of regex,
  with multi-strategy cover extraction.
- **Vendor README** at `assets/vendor/README.md` explaining how to host
  `epub.js` and `jszip` locally for tighter CSP.

### Changed

- **`index.php`**: completely rewritten. No longer scans the filesystem on
  every request — queries the `books` table via `BookRepository`.
- **CSS**: 1660-line `styles.css` split into `design-system.css`, `base.css`,
  `components.css`, `library.css`, `reader.css`, `admin.css`, `auth.css`.
  Design tokens (terracotta + teal palette, Instrument Serif + DM Sans
  typography) preserved.
- **JavaScript**: 1414-line `script.js` split into focused ES modules under
  `assets/js/{shared,reader}/`.
- **Storage layout**: books move from `books/` (web-served) to
  `storage/books/{shard}/{uuid}.epub` (deny-listed); covers move to
  `assets/covers/{uuid}.jpg`.

### Removed (closed vulnerabilities)

- **`upload.php`** — had NO authentication whatsoever. Anyone with the URL
  could upload arbitrary files. Replaced by `admin/upload.php` with full
  admin role check + multi-layer validation.
- **`upload_epub.php`** — used a hardcoded SHA-256 password placeholder
  (`PUT_YOUR_SHA256_HASH_HERE`). Replaced by proper login + admin role gate.
- **`update_metadata.php`** — used regex over EPUB OPF XML to rewrite ZIP
  contents in place. Brittle and could corrupt files. Metadata edits now
  write to the database; on-disk EPUBs stay canonical.
- **Tailwind CDN** (`cdn.tailwindcss.com`) — explicitly marked dev-only by
  upstream. Replaced by hand-rolled CSS in `assets/css/`.
- **Legacy thumbnail scripts** — `save_thumbnail.php`, `generate_thumbnails.php`,
  `thumbnail_admin.php` merged into `admin/thumbnails.php` and
  `ThumbnailService` class.

### Notes

- This release intentionally keeps `epub.js` pinned at 0.3.93 to avoid
  reader regressions during the refactor. The library will upgrade to
  current `epub.js` in the Phase 3 release alongside highlights / TTS work.
- Reading progress for guest sessions still lives in `localStorage` only;
  sign in (or create an account) to sync across devices.

## Roadmap

- **1.1.0 — Library UX**: Continue Reading rail, collections, book detail
  page, advanced search.
- **1.2.0 — Reader features**: Highlights & annotations, in-book search,
  dictionary popup, text-to-speech, dyslexia-friendly font.
- **1.3.0 — Polish**: Reading stats dashboard, immersive mode, offline
  reading via service worker, reviews, admin bulk actions.
