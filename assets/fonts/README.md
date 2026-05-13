# Vendored fonts

The reader loads `OpenDyslexic-Regular.woff2` and `OpenDyslexic-Bold.woff2`
from this directory when the user picks **OpenDyslexic** as their font.
If the files are missing, the browser silently falls back to the next
family in the stack (Iowan Old Style → Charter → serif).

## Download

Drop the official `.woff2` builds in here:

| File | Source |
|---|---|
| `OpenDyslexic-Regular.woff2` | https://opendyslexic.org/  →  Downloads  →  WOFF2 release |
| `OpenDyslexic-Bold.woff2`    | same release |

Or via shell:

```bash
cd assets/fonts/
curl -L -o OpenDyslexic-Regular.woff2 \
  https://github.com/antijingoist/opendyslexic/raw/master/compiled/OpenDyslexic-Regular.woff2
curl -L -o OpenDyslexic-Bold.woff2 \
  https://github.com/antijingoist/opendyslexic/raw/master/compiled/OpenDyslexic-Bold.woff2
```

OpenDyslexic is released under the SIL Open Font License 1.1 — please
include `OFL.txt` from the upstream release if you redistribute the font
alongside this app.

## Optional self-hosted UI fonts

The library + admin UIs currently load Instrument Serif and DM Sans from
Google Fonts (declared in `assets/css/design-system.css`). If you want
to break that dependency, host their `.woff2` files here as well and
update the `@import` in `design-system.css` to a local `@font-face` block.

`*.woff2` files in this directory are gitignored by default — see the
top-level `.gitignore`.
