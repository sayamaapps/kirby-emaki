# Changelog

All notable changes to this project are documented here. Versions follow
[Semantic Versioning](https://semver.org) with these project rules:

- **A change to the markup contract** (the `data-emaki*` attributes, the
  `emaki:*` events, the class names of the dialog) is at least a **minor**
  version and described under "Markup", so a site can re-check its templates
  and CSS.
- **A change to the CSS tokens** (`--emaki-*`) is listed under "Tokens".

## [Unreleased]

### Changed

- **Structure.** Classes live in `Sayamaapps\Emaki\` behind a class map; `index.php` only registers the plugin. Behaviour is unchanged.
- **Options** are read as `sayamaapps.emaki.<name>`. The older `emaki.<name>` still works and reports a deprecation in debug mode.
- **Functions** `emaki_option()`, `emaki_i18n()`, `emaki_asset_url()`, `emaki_tags()` and `emaki_attributes()` are wrappers now; the old class names `Emaki\*` are aliases. Both are removed in the next major version. See the README, Deprecations.
- **Labels** are Kirby translations `sayamaapps.emaki.<key>`. The old keys `emaki.<key>` stay registered with the same texts. `Emaki\I18n::t()` and `table()` stay as wrappers over the same texts and report a deprecation; they are removed in the next major version.

### Removed

- **`DATENSCHUTZ.md` and `PRIVACY.md`.** Emaki processes no visitor data, so it has no place in a site's privacy policy. The README says so under "Privacy".

## [1.0.0] — 2026-10-19

First release: the viewer (a `<dialog>` with keyboard, swipe, counter,
caption, focus return, reduced motion, loading state), the `emaki` snippet
for the tags, the `emaki/gallery` snippet and `$file->emaki()` for tiles and
large views from Kirby's own thumb presets, the `emaki:open` event for a
site's own counting, English and German labels.

Also in 1.0.0: `$file->emaki()` and `emaki_attributes()` return `null` when no
thumb can be created (media folder not writable, or a write fails) and never the
original; `emaki/gallery` leaves such an image out and marks it with an HTML
comment, and prints no container when no tile is left. A container nested in
another one is its own sequence. The README and the PHP docs say what `format`
and the preset entries do, and that the asset URL carries `<hash>-<time>` and no
query string.

Also in 1.0.0: the stage keeps its padding inside the dialog (`box-sizing:
border-box` on `.emaki-stage`). On a page without a global box-sizing reset its
height plus padding ran past the dialog: the caption fell below the visible area
and a portrait picture was cut off at the bottom.

**[privacy text]** `DATENSCHUTZ.md` / `PRIVACY.md`: Emaki loads only what the
markup links: the image that is opened and its two neighbours, which are fetched
in advance. An image that a page links from a foreign host is loaded from that
host when it is opened or fetched in advance.

Planned for 1.1: a deep link (`#emaki=3`) that opens on load and closes with
the back button. Zoom is not planned.
