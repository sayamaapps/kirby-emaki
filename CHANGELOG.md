# Changelog

All notable changes to this project are documented here. Versions follow
[Semantic Versioning](https://semver.org) with these project rules:

- **A change to the markup contract** (the `data-emaki*` attributes, the
  `emaki:*` events, the class names of the dialog) is at least a **minor**
  version and described under "Markup", so a site can re-check its templates
  and CSS.
- **A change to the CSS tokens** (`--emaki-*`) is listed under "Tokens".

## [1.2.0] — 2026-10-31

### Changed

- Options are read as `sayamaapps.emaki.<name>`. The 1.x names `emaki.<name>` keep working and are read without a warning. If both are set, the new one wins.
- `Options::OPTION_PREFIX` is new. `Options::PREFIX` keeps its 1.x value.

## [1.1.0] — 2026-10-27

### Added

- `Sayamaapps\Emaki\Viewer` with `prepare()`, `tags()` and `assetUrl()`. The snippets and the `emaki_*` wrappers use it.

### Changed

- **Structure.** Classes live in `Sayamaapps\Emaki\` behind a class map; `index.php` only registers the plugin. Behaviour is unchanged.
- **Error message.** A direct call of the old class `Emaki\Images::attributes()` with a wrong argument type still throws `TypeError`, but the message names `Sayamaapps\Emaki\Images::attributes()`. The function `emaki_attributes()` is unchanged.
- **Metadata.** `composer.json` has new keywords and tool settings; the installed package is unchanged.
- **Lock file.** `composer.lock` is committed and left out of the installed package. CI tests the lowest, the locked and the newest dependencies.
- **Names of 1.x are kept.** The `Emaki\*` classes are aliases, and the `emaki_*` functions are wrappers. Both are marked `@deprecated` in the code and the README; nothing reports a deprecation at runtime.
- **Tests** run on PHPUnit (`composer test`), the checks run in CI on PHP 8.2 to 8.5.
- **Options** are read as before, `emaki.<name>`. Labels come from Emaki's own tables, as before.
- **`I18n::t()` and `I18n::table()`** keep their 1.x behaviour.
- **Documentation.** The README lists the 1.x names and the changes planned for 2.0.

### Planned for 2.0

Breaking changes, not part of 1.x:

- The `emaki_*` functions and the `Emaki\*` class names are removed.
- Options move to `sayamaapps.emaki.<name>`; the `emaki.<name>` names are then deprecated and reported in Kirby's debug mode.
- Labels are read as Kirby translations `sayamaapps.emaki.<key>`.

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
