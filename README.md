# Emaki

Image viewer for [Kirby CMS](https://getkirby.com) 5: a dependency-free lightbox with `srcset` gallery tiles.

**Versions:** Kirby 5 · PHP 8.2 to 8.5 · MIT · [Changes](CHANGELOG.md)

Emaki (絵巻, a Japanese picture scroll) shows the images of a page one after another in a native `<dialog>`. Served from your own server, it uses no CDN, no inline code and no cookies. It styles only the viewer; the gallery grid is yours.

## Features

- Works with any markup: links to images in a `data-emaki` container open in the viewer.
- Keyboard, swipe and buttons of at least 48 px; navigation wraps around.
- Accessible: named dialog, polite counter, captions linked to the dialog, focus returned on close.
- Tiles and large views from Kirby's thumb presets; your originals are never written to.
- `emaki:open` event for your own counting; Emaki sends nothing itself.
- Reduced-motion aware, English and German labels, no cookies or browser storage.

## Requirements

- Kirby 5 (`getkirby/cms` 5.0 or newer)
- PHP 8.2, 8.3, 8.4 or 8.5
- A browser with the `<dialog>` element. Without it, links open the image.

## Installation

Pick one of three ways. The plugin always ends up in `site/plugins/emaki/`.

**Composer** (the plugin goes to `vendor/` and is linked into `site/plugins/`):

```sh
composer require sayamaapps/kirby-emaki
```

Add `site/plugins/emaki/` to `.gitignore` and deploy it like `vendor/`.

**ZIP:** download the release archive from the [releases page](https://github.com/sayamaapps/kirby-emaki/releases), unpack it and copy the folder to `site/plugins/emaki/`.

**Git submodule:**

```sh
git submodule add https://github.com/sayamaapps/kirby-emaki.git site/plugins/emaki
```

## Usage

### Setup

Switch Emaki on in `site/config/config.php` (off by default):

```php
return [
    'sayamaapps.emaki.active' => true,
];
```

Print the viewer's tags once per page:

```php
<?php snippet('emaki') ?>
```

Render a gallery from the images of a page. Style `.emaki-gallery` and `.emaki-item` yourself:

```php
<?php snippet('emaki/gallery', ['files' => $page->images()->sortBy('sort')]) ?>
```

### Own markup

Any container with `data-emaki` is one sequence; a container nested in another is its own. Links to image files inside it take part (`.jpg`, `.jpeg`, `.png`, `.gif`, `.webp`, `.avif`, `.svg`, or any link with `data-srcset`). `href` is the large view: a rendered variant, never the original file.

```html
<div data-emaki>
  <a href="/media/pages/courtyard/courtyard-2400x.jpg" data-emaki-caption="Our courtyard">
    <img src="/media/pages/courtyard/courtyard-720x.jpg" alt="Our courtyard" width="720" height="480" loading="lazy">
  </a>
</div>
```

Optional link attributes: `data-srcset` and `data-sizes` (large view), `data-emaki-caption` (empty means no caption; otherwise the `<img>` alt is the caption), `data-emaki-image` (passed to `emaki:open`). Without a container, or without `<dialog>`, Emaki creates nothing.

### `$file->emaki()`

`$file->emaki(array $options = [])` returns the attributes of one tile and its large view, or `null` when no thumb can be made. The `<a>` keys are `href`, `data-srcset` and `data-sizes`; the `<img>` keys are `src`, `srcset`, `sizes`, `width`, `height`, `alt`, `loading` and `decoding`. `href` is the largest large view. `src` is the smallest tile at least 720 px wide, or the largest tile. A file Kirby cannot resize (SVG) keeps its own URL and has no `srcset`. Add the caption and image name yourself.

Options per call: `large` and `tile` (a `thumbs.srcsets` preset name or a list of widths), `sizes`, `format` and `quality`. The call value wins over the option of the same name.

Kirby creates the thumbs on the first render: up to eight per image by default. After a deploy that empties the media folder, load the gallery pages once, or configure fewer widths. If a thumb cannot be written, the gallery leaves that image out with an HTML comment, and `$file->emaki()` returns `null`. A resizable image never falls back to its original.

### Counting

Emaki fires `emaki:open` on the link of each image shown: when the viewer opens and on every step. The event bubbles. `detail` holds `href`, `image` (`data-emaki-image` or `null`), `index` (0-based) and `count`.

```js
document.addEventListener('emaki:open', (event) => {
  const { href, image, index, count } = event.detail;
  const body = JSON.stringify({ image: image ?? href, position: index + 1, of: count });
  navigator.sendBeacon('/emaki-count', body); // an endpoint you write
});
```

### Styling

Set the colour tokens on `:root`, or on any ancestor of the dialog:

| Token | Default | Meaning |
|---|---|---|
| `--emaki-bg` | `rgb(12 16 20 / 0.88)` | Backdrop behind the image. |
| `--emaki-fg` | `#f4f5f3` | Text and button symbols. |
| `--emaki-accent` | `#ffffff` | Focus ring of the buttons and the counter. |

Emaki styles `.emaki` (the dialog) and its parts: `.emaki-stage`, `.emaki-image`, `.emaki-caption`, `.emaki-counter`, `.emaki-close`, `.emaki-prev` and `.emaki-next`. States: `.emaki--motion` (animation, only without reduced motion) and `.is-loading` (while an image loads).

## Options

Options are read as `sayamaapps.emaki.<name>`, flat or nested; `null` means "not set". The older spelling `emaki.<name>` still works as a fallback until the next major version, see [Deprecations](#deprecations). Scope: `config` for the site config, `call` for `$file->emaki()`, `gallery` for snippet variables.

| Option | Scope | Default | Meaning |
|---|---|---|---|
| `active` | config | `false` | Master switch; only `true` turns Emaki on. Off: no tags, plain gallery links, `$file->emaki()` still works. |
| `language` | config | request, default language, `locale`, then `en` | Label language, `en` or `de`. |
| `texts` | config | none | Label overrides, flat or per language (keys below). |
| `script` | config | `true` | `false` omits the `<script>` from the tags, for sites that bundle `emaki.js`. |
| `style` | config | `true` | `false` omits the `<link>`, for sites that bundle `emaki.css`. |
| `srcset` | config | `'emaki'` | Large-view widths: a `thumbs.srcsets` preset or a list. Without a preset: 800, 1200, 1800, 2400. |
| `thumbnail` | config | `'emaki-tile'` | Tile widths, same rules. Without a preset: 320, 480, 720, 960. |
| `sizes` | config | `'100vw'` | `sizes` of the large view, written as `data-sizes`. |
| `format` | config, call | `null` | Thumb format, such as `webp`. A call value wins; `null` uses Kirby's `thumbs.format`, else the file's own format. |
| `quality` | config, call | `null` | Thumb quality. A call value wins; `null` uses Kirby's `thumbs.quality`. |
| `large` | call | `srcset` option | Large-view preset or widths for this call. |
| `tile` | call | `thumbnail` option | Tile preset or widths for this call. |
| `sizes` | call, gallery | `'(max-width: 40rem) 100vw, 33vw'` | `sizes` of the tile `<img>`, not the large view (the `sizes` option). |
| `files` | gallery | none | Files or any iterable; non-images are skipped. |
| `caption` | gallery | `'alt'` | `'alt'`: the alt text. A field name: that field's value. `false`: none (empty `data-emaki-caption`). |
| `image` | gallery | none | File field whose value becomes `data-emaki-image`, for counting. |
| `class` | gallery | none | Extra classes on the container. |
| `tag` | gallery | `'div'` | Container element. |
| `attrs` | gallery | none | Extra container attributes; only `data-*`, `aria-*` and `id` are kept. |

A preset entry contributes only its width; its `quality` and `format` are ignored, and the options apply.

Example:

```php
return [
    'sayamaapps.emaki.active'    => true,
    'sayamaapps.emaki.language'  => 'de',
    'sayamaapps.emaki.srcset'    => 'emaki',
    'sayamaapps.emaki.format'    => 'webp',
    'sayamaapps.emaki.texts'     => ['de' => ['button.close' => 'Schließen']],
];
```

Override labels with `texts`: flat for all languages (`'sayamaapps.emaki.texts' => ['button.close' => 'Done']`) or per language. Keys and English defaults: `dialog` (`Image {n} of {count}`), `counter` (`{n} / {count}`), `button.close` (`Close`), `button.prev` (`Previous image`), `button.next` (`Next image`). German ships too. The labels are Kirby translations as `sayamaapps.emaki.<key>`.

## Snippets and functions

| Name | Use |
|---|---|
| `snippet('emaki')` | The viewer's `<link>` and `<script>`, once per page; prints nothing while Emaki is off. |
| `snippet('emaki/gallery', [...])` | A container with one tile per image. Variables: see Options (gallery scope). |
| `$file->emaki([...])` | The attributes of one tile and its large view, or `null`. |

A site that needs its own markup creates `site/snippets/emaki/gallery.php`; Kirby uses that file instead of Emaki's.

## Deprecations

Nothing is removed before the next major version. The old names below keep working. Options, functions, `I18n::t()` and `I18n::table()` report a deprecation in Kirby's debug mode; translation keys and class names do not.

| Older name | Use instead |
|---|---|
| Option `emaki.<name>` | `sayamaapps.emaki.<name>` (the new name wins when both are set) |
| Translation key `emaki.<key>` | `sayamaapps.emaki.<key>`. The old key stays registered with the same text, but the viewer reads the new one: a site's own translation of the old key does not reach the viewer, so use `texts` or the new key. |
| `emaki_option($key, $default)` | `Sayamaapps\Emaki\Options::get(App::instance(), $key, $default)` |
| `emaki_tags()` | `Sayamaapps\Emaki\Viewer::tags(App::instance())` |
| `emaki_i18n()` | `Sayamaapps\Emaki\Viewer::prepare(App::instance())` |
| `emaki_asset_url($file)` | `Sayamaapps\Emaki\Viewer::assetUrl(App::instance(), $file)` |
| `emaki_attributes($file, $options)` | `Sayamaapps\Emaki\Images::attributes($file, $options)` |
| `Emaki\I18n::t($key, $values)` | Kirby's translations: `I18n::translate('sayamaapps.emaki.<key>')`, with the placeholders filled in by the site |
| `Emaki\I18n::table($language)` | Kirby's translations: `I18n::translate('sayamaapps.emaki.<key>')` |
| Classes `Emaki\Images`, `Emaki\I18n`, `Emaki\Options`, `Emaki\Presets` | the same names in `Sayamaapps\Emaki\` (aliases) |

## Privacy

Emaki processes no visitor data: no cookies, no browser storage, no tracking. It requests only your own files and the images your markup links (the open image and its two neighbours). An image on another host is fetched from that host, which sees the request. Nothing needs to be added to a privacy policy while the images come from your own server.

## Maintenance

Tests: `composer test` (PHP) and `npm ci --prefix tests/browser && npm --prefix tests/browser run test:web` (browser). Checks: see [CONTRIBUTING](.github/CONTRIBUTING.md). Issues and pull requests are welcome, without a support promise. Report security problems privately through GitHub's vulnerability reporting ([SECURITY](.github/SECURITY.md)).

## License

MIT, see [LICENSE.md](LICENSE.md).
