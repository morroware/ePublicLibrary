# Changelog

## 1.1.0 — Library UX

Phase 2 of the multi-phase refactor. Builds discovery and curation on top
of the foundation: rails on the home page, a richer book detail experience,
user shelves, public reviews, an advanced search with structured filters,
and genre browse pages.

### Added

- **Continue Reading rail** on the home page for signed-in users. Each
  card shows cover, title, author, percent complete, and time since last
  read; click resumes at the saved CFI.
- **Recently added** and **Top rated** rails on the home page (both
  guest- and user-visible). Top rated only surfaces books with at least
  one review.
- **Home shortcuts** for logged-in users — quick links to *Your shelves*
  and *Advanced search*.
- **Book detail page** (`book.php?b={uuid}`) substantially upgraded:
  - Star ratings + review distribution histogram
  - Inline write-review form (one review per user per book, edit + delete)
  - Reviews list with author name, avatar, date, optional title + body
  - "Add to shelf" dropdown listing all of the user's shelves with
    membership toggle
  - "You might also like" rail of related books (shared tags)
  - Tag chips link out to the genre browse page
- **Shelves / collections**:
  - System shelves (Favorites, Want to Read, Finished) auto-seeded per user
  - Create / rename / delete / public-visibility custom shelves
  - Shelf mosaic preview (first four covers)
  - Per-shelf detail view with remove-from-shelf controls
  - Public shelf URLs (`collection.php?slug=...&user={uuid}`)
- **Advanced search** (`search.php`) with structured filters:
  - Free-text query (FULLTEXT `MATCH ... AGAINST` in BOOLEAN MODE)
  - Genre, language, year range, minimum rating
  - Sort by relevance / title / author / date / rating / popularity
- **Genre browse pages** (`genre.php?slug={tag-slug}`) — paginated list of
  every book tagged with that genre.
- **Book aggregates on the `books` table**: `review_count` and `avg_rating`
  columns kept fresh by `AFTER INSERT/UPDATE/DELETE` triggers on reviews
  (migration 0014). Sub-50ms responses for rating-sorted queries.
- **`ReviewRepository`** class with upsert/delete/distribution helpers.
- **`BookRepository`** gains FULLTEXT-aware `paginate()` with filters
  (`tag_slug`, `language`, `year_min`/`year_max`, `min_rating`,
  `sort_by` including `relevance`/`rating`/`popular`) plus
  `recentlyAdded()`, `topRated()`, `mostRead()`, `relatedTo()`,
  `distinctLanguages()`, `yearRange()`.
- **`CollectionRepository`** gains full CRUD (`create`, `update`, `delete`),
  `addBook`, `removeBook`, `reorder`, `forUserAndBook` (powering the
  Add-to-shelf UI), book listing with position-based ordering.
- **JSON APIs** for inline UX:
  - `api/shelves.php` — list user's shelves with membership for a book;
    POST `{uuid, collection_id, action: add|remove|toggle}` toggles.
  - `api/reviews.php` — list / upsert / delete reviews. Public GET; POST
    and DELETE require auth.
- **Header user menu** gains *Your shelves* and *Advanced search*
  entries.

### Changed

- **Library home view** now branches: with no search/filter active, shows
  the rail-driven home page; once any filter is applied, shows the standard
  paginated grid (which now honors all the new filter parameters).
- **Pagination partial** accepts a `baseUrl` so it can be reused on
  `genre.php` and elsewhere without hardcoding `index.php`.
- **Mobile sort controls** restored: previously hidden via `display:none`
  on small viewports. Now wrap onto their own row with 44px-min tap
  targets. A global `@media (pointer: coarse)` rule enforces the 44×44
  minimum on all icon buttons (theme toggle, reader chrome, etc.).
- **Book detail wrapper** is now the source of truth for book-page styling
  (lives in `assets/css/discovery.css`); the inline `<style>` block in
  the old Phase 1 view is removed.

### Migration notes

- Apply migration `0014_book_aggregates.sql` via `/admin/migrate.php`.
  The migration backfills `review_count` and `avg_rating` from any
  existing reviews (no-op on a fresh install) and installs three
  triggers (`reviews_after_insert`, `_update`, `_delete`) that keep the
  columns fresh.
- No data migrations needed for shelves — existing users already have
  system shelves seeded at registration (Phase 1 behaviour).
- Triggers are dropped (`DROP TRIGGER IF EXISTS`) and recreated, so
  re-running the migration is safe.

---

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

- **1.2.0 — Reader features**: Highlights & annotations, in-book search,
  dictionary popup, text-to-speech, dyslexia-friendly font, epub.js upgrade.
- **1.3.0 — Polish**: Reading stats dashboard, immersive mode, offline
  reading via service worker, admin bulk actions, email-based password
  reset.
