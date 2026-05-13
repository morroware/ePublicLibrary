# Vendored JavaScript libraries

The reader page loads `epub.js` and `jszip` from this directory if the files
exist; otherwise it falls back to the public CDN. Hosting them locally
tightens your Content-Security-Policy (no third-party origins) and removes
a dependency on external uptime.

## What to vendor

| File | Version | Source |
|---|---|---|
| `epub.min.js`  | 0.3.93 | https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js |
| `jszip.min.js` | 3.1.5  | https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.5/jszip.min.js |

## How to vendor (one-time, on your computer or server with shell access)

```bash
cd assets/vendor/
curl -L -o epub.min.js  https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js
curl -L -o jszip.min.js https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.5/jszip.min.js
```

If you don't have shell access, download the two files in a browser and upload
them via FTP / cPanel File Manager into this directory.

## Verifying integrity

Add Subresource Integrity hashes to `views/layouts/reader.php` after vendoring:

```bash
openssl dgst -sha384 -binary epub.min.js  | openssl base64 -A
openssl dgst -sha384 -binary jszip.min.js | openssl base64 -A
```

Then update the `<script>` tags in `views/layouts/reader.php` with
`integrity="sha384-..." crossorigin="anonymous"` attributes.

## Upgrade path

The reader is pinned to epub.js 0.3.93 in Phase 1. Phase 3 upgrades to the
current version alongside the highlights work — see the refactor plan.
