> **Names:** classes `Sayamaapps\Emaki\*` (the 1.x names `Emaki\*` are aliases), options `sayamaapps.emaki.*` (the 1.x names `emaki.*` are still read as a fallback), functions `emaki_*` are wrappers in `legacy.php`. The list below gives the names of 1.x.

# Emaki — specification v1

Emaki (絵巻, the picture scroll) is an image viewer for Kirby 5 galleries
plus a thin gallery helper. This file is the contract between the parts:
`index.php` and this file are the contract; the files named in §7 implement
it. Anything not written here is decided by the maintainer, not invented
while coding.

## 1. Decisions (defaults in force; "open" = may still change)

1. **Scope v1 = viewer + thin feeder.** The viewer (dialog, keyboard, swipe,
   counter, caption, focus return, reduced motion, loading state) and a feeder
   that renders `<a href><img srcset>` tiles from Kirby's own thumb presets:
   snippet `emaki/gallery` and `$file->emaki()`. Both feeders are optional —
   the viewer works on any `<a href><img>` inside `[data-emaki]`, whatever
   rendered it.
2. **No image pipeline.** No `<picture>`, no AVIF, no build step, no files
   under `public/`, no KirbyTag or image block override, no Panel field. Sites
   that need art direction use a dedicated image plugin; Emaki just needs
   `<a href><img>`.
3. **Counting is decoupled.** Emaki fires `emaki:open` (§4.6). It never posts
   anywhere. A site (or Kizami) listens.
4. **No zoom** (not planned). **No deep link in v1** (`#emaki=3`, planned 1.1).
5. **The large view is a rendered variant, never the original.** `href` and
   `data-srcset` come from Kirby thumbs (§2.3).
6. **Tokens: three** — `--emaki-bg`, `--emaki-fg`,
   `--emaki-accent` are the public tokens (README documents only these).
   `--emaki-accent` has exactly two uses: the focus ring of the buttons and
   the current counter. The four derived values (`--_muted`, `--_border`,
   `--_radius`, `--_font`, §5) stay internal: the stylesheet reads
   `--emaki-muted` etc. when a site sets them, but they are neither
   documented nor promised and may change.
7. **Language.** Public interface, README, code and comments in English. Labels
   translatable, English first, German shipped (§6).
8. **Off by default** (`sayamaapps.emaki.active`), like every sayamaapps module. Off:
   `emaki` snippet prints nothing, `emaki/gallery` renders the same markup
   without `data-emaki` (plain links to the large image), `$file->emaki()`
   still works (plain data).
9. **Forbidden anywhere in the package:** inline `<style>`/`<script>`, `style`
   attributes, `element.style.*`, requests to anything but the site itself,
   browser storage, touching or deleting originals, wrapping images that are
   not inside `[data-emaki]`, own gallery grid design (only the stage is
   styled; `emaki-gallery`/`emaki-item` carry no visual rules).

## 2. PHP API (index.php)

### 2.1 Options (prefix `sayamaapps.emaki.`, flat; 1.x `emaki.` as fallback; `null` = not set)

| Option | Default | Meaning |
|---|---|---|
| `active` | `false` | master switch; only `true` switches on |
| `language` | request language → default language → `locale` → `en` | labels |
| `texts` | – | overrides per key, flat or per language (§6) |
| `script` | `true` | `false`: `emaki_tags()` leaves out the `<script>`. A site that bundles `emaki.js` itself must then render the label attributes on its own script tag (§6: key `dialog` → `data-dialog`, …); `emaki_tags()` is the only producer |
| `style` | `true` | `false`: `emaki_tags()` leaves out the `<link>` |
| `srcset` | `'emaki'` | preset name in `thumbs.srcsets` **or** a list of widths for the **large view**; a missing preset falls back to `Options::LARGE_WIDTHS` = 800, 1200, 1800, 2400 |
| `thumbnail` | `'emaki-tile'` | same for the **tile**; fallback `Options::TILE_WIDTHS` = 320, 480, 720, 960 |
| `sizes` | `'100vw'` | `sizes` of the **large view** (`data-sizes`). The per-call `sizes` of `emaki()` / `emaki/gallery` is the **tile** `sizes` (§2.3): same word, different scope, both kept |
| `format` | `null` | thumb format for both (`'webp'`, `'jpg'`…). `null` = not set: a per-call `format` (§2.3) falls back to this; if both are `null`, Kirby's `thumbs.format` applies, otherwise the file keeps its own format |
| `quality` | `null` | thumb quality; `null` = Kirby's `thumbs.quality` |

### 2.2 Functions and methods

- `emaki_option(string $key, mixed $default = null): mixed`
- `emaki_i18n(): void` — sets language and overrides for the request.
- `emaki_asset_url(string $file): ?string` — `/media/plugins/sayamaapps/emaki/<hash>-<time>/<file>` on the site, no query string.
- `emaki_tags(): string` — once per page: `<link rel="stylesheet" href="…">` and
  `<script src="…" defer data-dialog="…" data-close="…" data-prev="…" data-next="…" data-counter="…"></script>`.
  Empty string when off. Attribute values are `esc()`-ed; the script reads
  them from `document.currentScript.dataset`.
- `emaki_attributes(File $file, array $options = []): ?array` and
  `$file->emaki(array $options = []): ?array` → `Emaki\Images::attributes()`.
- Snippets `emaki` (prints `emaki_tags()`) and `emaki/gallery` (§3.2).

### 2.3 `Emaki\Images::attributes(File $file, array $options = []): ?array`

Per-call `$options`: `sizes` (tile sizes, default `'(max-width: 40rem) 100vw, 33vw'`),
`large` (preset name or widths; default option `srcset`), `tile` (default option
`thumbnail`), `format`, `quality` (defaults: the options). Returns one flat array:

| Key | For | Value |
|---|---|---|
| `href` | `<a>` | URL of the **largest rendered large-view variant** (never `$file->url()` for a resizable image) |
| `data-srcset` | `<a>` | large-view srcset `url 800w, url 1200w, …`; `null` if not resizable |
| `data-sizes` | `<a>` | option `sizes` |
| `src` | `<img>` | URL of the tile variant closest to **but not below** 720 px, else the largest tile variant |
| `srcset` | `<img>` | tile srcset; `null` if not resizable |
| `sizes` | `<img>` | per-call `sizes` |
| `width`, `height` | `<img>` | intrinsic dimensions of the **tile `src` variant** (ints; aspect ratio of the oriented file) |
| `alt` | `<img>` | `$file->alt()` or `''` |
| `loading` | `<img>` | `'lazy'` |
| `decoding` | `<img>` | `'async'` |

Rules: thumbs are made with `['width' => w, 'format' => …, 'quality' => …, 'autoOrient' => true]`;
format/quality keys are left out when null. Widths come from
`Emaki\Presets::widths()` (§2.4): never above the file's width, always
including `min(fileWidth, max(configured))`, so with the default list a
1000 px file gets the variants 800 and 1000 px (`href` = the 1000 px variant,
still `href !== $file->url()`), and a 600 px file gets one variant at 600 px. Files that are
not resizable (`!$file->isResizable()`, SVG, width < 1) or not images get
`href = $file->url()`, `src = $file->url()`, both srcsets `null`, the file's
own width/height or `null`. Originals are never written to. Each thumb is saved
as its URL is rendered, so every URL points to a file that exists. Returns null when no thumb can be created (media folder not writable, or a write fails); the gallery leaves the image out and marks that in an HTML comment. The original is never linked in that case.

### 2.4 `Emaki\Presets::widths(App $kirby, string|array|null $preset, array $fallback, int $fileWidth): int[]`

Accepts a preset name (looked up in `thumbs.srcsets.<name>`; Kirby's two
spellings: `width => 'condition'` and a plain list, also `width => [options]`
where `width` is the option key), a list of ints, or null (= `$fallback`).
Returns ascending unique ints `< $fileWidth` plus `min($fileWidth, max(list))`.
Non-numeric or ≤ 0 entries are ignored; an empty result falls back to
`[min($fileWidth, max($fallback))]`.

## 3. Markup contract

### 3.1 What the script needs (any renderer)

```html
<div data-emaki>                                     <!-- one container = one sequence -->
  <a href="/media/…/photo-2400x.jpg"                 <!-- large view; a rendered variant -->
     data-srcset="/media/…-800x.jpg 800w, …"         <!-- optional -->
     data-sizes="100vw"                              <!-- optional, default 100vw -->
     data-emaki-caption="Our courtyard"              <!-- optional; wins even when empty -->
     data-emaki-image="courtyard">                    <!-- optional; passed to emaki:open -->
    <img src="…" srcset="…" sizes="…" width="960" height="640" alt="Our courtyard" loading="lazy" decoding="async">
  </a>
</div>
<?php snippet('emaki') ?>
```

- A link takes part when its `href` ends in `.jpe?g|png|gif|webp|avif|svg`
  (query string allowed) **or** it has `data-srcset`. Other links in the
  container (a download link, say) are left alone.
- Caption: `data-emaki-caption` if present (even empty = no caption), else the
  inner `<img alt>`, else none.
- Several containers = several independent sequences. A container inside another one is
  its own sequence: a link belongs to the nearest `data-emaki` container around it.

### 3.2 Snippet `emaki/gallery`

Variables: `$files` (Files or iterable; non-images skipped), `$sizes`,
`$caption` (`'alt'` default | field name | `false` = render `data-emaki-caption=""`, so the viewer shows no caption and does not fall back to alt), `$class`, `$tag`
(default `div`), `$attrs` (only `data-*`, `aria-*`, `id`; others dropped, `data-emaki` always dropped because it is Emaki's own),
`$image` (file field whose value becomes `data-emaki-image`; default none).

Renders:

```html
<div class="emaki-gallery[ class]" data-emaki[ attrs]>
  <a class="emaki-item" href="…" data-srcset="…" data-sizes="…" data-emaki-caption="…"[ data-emaki-image="…"]>
    <img src="…" srcset="…" sizes="…" width="…" height="…" alt="…" loading="lazy" decoding="async">
  </a>
  …
</div>
```

`data-emaki` only while `sayamaapps.emaki.active` is true. Attribute values go through
`esc()`. Empty `$files` renders nothing. An image without thumbs (§2.3 returns
null) is left out: its place is `<!-- emaki: no thumbs for <filename> -->`, with
no link and no image. The container is printed only when at least one tile
remains, and the comments of the images left out stay inside it; when no tile
remains, only the comments are printed, without container. No `<figure>`, no
caption markup in the grid: the site decides how tiles look.

## 4. Viewer behaviour (assets/emaki.js)

4.1 **Start.** Runs once after `defer`. Reads labels from
`document.currentScript.dataset` (`dialog`, `close`, `prev`, `next`,
`counter`; placeholders `{n}` and `{count}`), falling back to English. If
there is no `[data-emaki]` or no `window.HTMLDialogElement`, it does nothing
(no element created). ES2017, one IIFE, no build step, no dependencies,
≈ 9–10 kB unminified.

4.2 **Dialog**, built once and appended to `<body>`:

```html
<dialog class="emaki" aria-label="Image 3 of 7">
  <figure class="emaki-stage">
    <img class="emaki-image" alt="">
    <figcaption class="emaki-caption" id="emaki-caption"></figcaption>
  </figure>
  <button type="button" class="emaki-close" aria-label="Close">×</button>
  <button type="button" class="emaki-prev" data-step="-1" aria-label="Previous image">‹</button>
  <button type="button" class="emaki-next" data-step="1" aria-label="Next image">›</button>
  <p class="emaki-counter" aria-live="polite">3 / 7</p>
</dialog>
```

4.3 **Open** on a plain left click on a participating link (not modified,
not middle; `defaultPrevented` respected): `preventDefault`, remember the
link as the origin, show image i, `showModal()`.

4.4 **Show image i** (wrapping): set `img.srcset` from `data-srcset` (or
remove it), `img.sizes` from `data-sizes` or `100vw`, `img.src` from `href`.
Add class `is-loading` to the dialog until the image's `load` or `error`.
Caption per §3.1: text into the figcaption, `hidden` when empty; when a
caption is shown the `<img alt>` is `''` and the dialog gets
`aria-describedby="emaki-caption"`; without caption `alt` = the tile's alt and
`aria-describedby` is removed. `aria-label` of the dialog = label `dialog`
with `{n}`/`{count}` filled. Counter text = label `counter`; counter and the
two arrows are `hidden` for a sequence of one. Preload the two neighbours
(`new Image().src`). Fire `emaki:open` (§4.6).

4.5 **Navigation.** Arrow buttons; keys `ArrowLeft`/`ArrowRight` (±1),
`Home`/`End` (first/last), `Escape` (native close); swipe ≥ 45 px, horizontal
only; click on anything that is not the image, caption or a button closes.
On `close`: focus back to the origin link (`preventScroll`), `src`/`srcset`
removed so the next opening does not flash the old image. Class
`emaki--motion` on the dialog unless `prefers-reduced-motion: reduce`.

4.6 **Event.** For every image shown (on open and on each navigation):
`link.dispatchEvent(new CustomEvent('emaki:open', { bubbles: true, detail: { href, image, index, count } }))`
where `image` = `data-emaki-image` or `null`, `index` 0-based. Nothing else
leaves the script: no `fetch`, no `navigator.sendBeacon`.

4.7 **Tests** (`tests/emaki-js.test.mjs`, node:test + fake DOM, no dependencies): keeps
the guard tests already there and adds: opens on click and shows the right `src`/`srcset`/`sizes`;
caption rules (attribute wins, empty attribute = none, alt fallback,
`alt=''` when caption shown); counter and labels with placeholders; wrap
around, Home/End; `hidden` arrows for one image; `emaki:open` detail on open
and navigation; focus return on close; modified clicks and non-image links
untouched; no element created without `[data-emaki]`.

## 5. Stylesheet (assets/emaki.css)

Rules only for `.emaki` and its parts; no rule for `.emaki-gallery` or
`.emaki-item` beyond none. Tokens read with fallbacks:

```css
.emaki {
  --_bg: var(--emaki-bg, rgb(12 16 20 / 0.88));
  --_fg: var(--emaki-fg, #f4f5f3);
  --_accent: var(--emaki-accent, #ffffff);
  /* derived, so four more tokens can be added later without touching markup: */
  --_muted: var(--emaki-muted, color-mix(in srgb, var(--_fg) 70%, transparent));
  --_border: var(--emaki-border, color-mix(in srgb, var(--_fg) 35%, transparent));
  --_radius: var(--emaki-radius, 2px);
  --_font: var(--emaki-font, inherit);
}
```

Behaviour to keep from the reference viewer: full-viewport dialog
(`max-width: 100vw; max-height: 100dvh`), backdrop in `--_bg`, stage as a
grid `1fr auto` centred, image `width/height: auto; max-width/height: 100%`
(**not** `object-fit`, so a click beside a portrait image hits the stage),
buttons ≥ 48 px, `hidden` honoured, counter top centre, open animation only
with `.emaki--motion`, `is-loading` shows a subtle placeholder background on
the image box. ≈ 4 kB. No `@import`, no `url(http…)`.

## 6. Labels (translations/*.php; the keys are the contract)

`dialog` "Image {n} of {count}", `counter` "{n} / {count}", `button.close`,
`button.prev`, `button.next`. Also registered as Kirby translations
`emaki.<key>`. Overrides via `sayamaapps.emaki.texts`.

## 7. Files and owners

**Core:** `index.php`, `src/I18n.php`, `src/Options.php`,
`translations/*.php`, `snippets/emaki.php`, `composer.json`, CI, CHANGELOG,
`tests/I18nTest.php`, the four guard tests, this file.

**(a) JS/CSS:** `assets/emaki.js`, `assets/emaki.css`,
`tests/emaki-js.test.mjs` (extend). Acceptance: §4 and §5 complete;
`node --test tests/` green; `node --check` green; the CI greps pass (no
storage, no foreign address, no inline style); file sizes within ±20 % of the
estimates; every label from §6 used, none hard-coded except the English
fallback.

**(b) PHP:** `src/Images.php`, `src/Presets.php`,
`snippets/emaki/gallery.php`, `tests/PresetsTest.php` (Kirby-free: widths
rules of §2.4 incl. both preset spellings, capping, fallbacks),
`tests/KirbyTest.php` (isolated Kirby in a temp dir, real generated JPEG/PNG/
WebP via GD, a rotated JPEG with EXIF orientation 6 — pattern: write a
minimal APP1 block — and an SVG; checks: every key of §2.3, `href` ≠ original
and ends in the largest width, `data-srcset` widths, `src` ≥ 720 rule,
oriented width/height, SVG passthrough, no upscaling for a 300 px file,
`emaki/gallery` markup of §3.2 with and without `active`, `$attrs` filter,
`caption` modes, `esc()` of a hostile alt, `emaki` snippet prints both tags
once and nothing when off, `sayamaapps.emaki.texts` reaches the script tag, no inline
style/script in any output (regex check of the output), the original's
SHA-256 unchanged). Acceptance: `composer test` green on PHP 8.2–8.4.

**(c) Browser tests:** `tests/browser/package.json` (`@playwright/test`,
`@axe-core/playwright`, scripts `test:web`), `tests/browser/playwright.config.ts`
(webServer `php -S 127.0.0.1:<port> -t .` from the repository root, project
chromium, global setup `tests/browser/make-fixture-images.php`), fixture
`tests/fixture/index.html` (three small JPEGs generated with GD into
`tests/fixture/img/`, not committed; a `[data-emaki]` gallery using the attributes of §3.1
by hand, a second container, a non-image link, the `emaki` tags written out
by hand with German labels), `tests/browser/emaki.spec.ts` (open by click,
←/→/Home/End, Esc, click beside the image, focus return, dialog accessible
name "Bild 2 von 3", counter, caption shown/hidden, `emaki:open` detail
captured via `page.evaluate`, non-image link opens normally, axe with no
violations while open, no `[style]` in the document, viewport 390 and 1280),
and the CI job `browser` added to `.github/workflows/ci.yml` (copy the
`script` job's runner lines; `npx playwright install chromium`; `xvfb-run -a
npx playwright test --workers=2`; artifact of `tests/browser/test-results/` on failure).
Acceptance: green locally and in CI.

**(d) Docs:** `README.md` (structure of a sayamaapps module README:
intro with the name's meaning, feature list, requirements, installation with
the `repositories` block until Packagist, quick start, the markup contract,
`emaki/gallery` and `$file->emaki()` with all options, counting with the
event (a 10-line listener example), styling with the tokens, configuration
table, privacy, the "maintained for our own projects, issues welcome, no
support promise" note, licence). Fictional examples only. Acceptance: every
option, snippet variable, attribute, event and token of this SPEC appears in
the README; no term that is not in the SPEC.

## 8. Tests and CI

`composer test` runs the PHPUnit suite; `node --test tests/*.test.mjs` the
script tests; Playwright (`tests/browser`) the browser tests. CI (`.github/workflows/ci.yml`): jobs `tests` (PHP 8.2–8.4), `script`,
`browser` on GitHub-hosted Ubuntu runners; fork PRs never run there.
Every PR shows green before merge.

## 9. Release

Version `1.0.0` in the CHANGELOG with its date; annotated tag `v1.0.0`.
Packagist later.
