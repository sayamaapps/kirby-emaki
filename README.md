# Emaki

Image viewer for [Kirby CMS](https://getkirby.com) 5: a dependency-free lightbox built on the native `<dialog>` element, served from your own server, plus a thin gallery helper that renders `srcset` tiles and large views from Kirby's own thumb presets. No CDN, no inline code, no cookies.

*Emaki* (絵巻) is the Japanese picture scroll: one picture after the other, unrolled by hand. The viewer does the same with the images of a page. A gallery becomes one sequence, and the visitor leafs through it one image at a time.

- **Works with any markup.** A link to an image inside a `data-emaki` container opens in the viewer, whatever rendered it.
- **Keyboard, swipe and buttons.** Arrow keys, Home and End, Escape, a horizontal swipe, and buttons of at least 48 px. Navigation wraps around.
- **Accessible.** The dialog has an accessible name with the position, the counter is announced politely, captions are linked to the dialog, and focus returns to the link when the viewer closes.
- **Motion and loading.** Animations run only when the visitor does not prefer reduced motion. A placeholder shows while an image loads, and the neighbours are preloaded.
- **Thumbs from Kirby.** `emaki/gallery` and `$file->emaki()` render tiles and large views from Kirby's thumb presets. The large view is a rendered variant, never the original file, and Emaki never writes to your originals.
- **Counting by event.** Emaki fires `emaki:open` for every image shown. It sends nothing itself; your site decides what to count.
- **Tokens for colours.** Three CSS custom properties set the viewer's colours. Emaki styles the viewer only; the gallery grid is yours.
- **English and German.** Labels are translatable and also registered as Kirby translations.
- **Off by default.** Nothing is printed until you set `emaki.active` to `true`.
- **No cookies, no browser storage. Emaki requests only its own files and the images your markup links: the open image and its two neighbours, which are fetched in advance.**

Emaki is deliberately small. It does not produce `<picture>` elements or AVIF, has no build step, no KirbyTag or image block override and no Panel field. Sites that need art direction use a dedicated image plugin; Emaki needs only `<a href><img>`. Zoom and deep links (`#emaki=3`) are not part of version 1.0.

## Requirements

- PHP 8.2–8.4
- Kirby 5
- A browser with the `<dialog>` element, which every current browser has. Without it the links simply open the image.

## Installation

```sh
composer require sayamaapps/kirby-emaki
```

Until the package is on Packagist, add the repository to your `composer.json` first:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/sayamaapps/kirby-emaki" }]
```

## Quick start

1. Switch Emaki on in `site/config/config.php`:

   ```php
   return [
       'emaki.active' => true,
   ];
   ```

2. Print the viewer's tags once per page, where the site prints its other tags (in the head or at the end of the body). The script is deferred:

   ```php
   <?php snippet('emaki') ?>
   ```

3. Render a gallery from the images of a page:

   ```php
   <?php snippet('emaki/gallery', ['files' => $page->images()->sortBy('sort')]) ?>
   ```

The gallery prints its tiles without any layout. Style the `emaki-gallery` grid and its `emaki-item` tiles yourself.

## Markup contract

The viewer does not care how the markup was made. Any container with `data-emaki` becomes a sequence of images:

```html
<div data-emaki>                                     <!-- one container = one sequence -->
  <a href="/media/pages/courtyard/courtyard-2400x.jpg"          <!-- large view, a rendered variant -->
     data-srcset="/media/pages/courtyard/courtyard-800x.jpg 800w, /media/pages/courtyard/courtyard-1200x.jpg 1200w, /media/pages/courtyard/courtyard-1800x.jpg 1800w, /media/pages/courtyard/courtyard-2400x.jpg 2400w"
     data-sizes="100vw"
     data-emaki-caption="Our courtyard"
     data-emaki-image="courtyard">
    <img src="/media/pages/courtyard/courtyard-720x.jpg" srcset="…" sizes="(max-width: 40rem) 100vw, 33vw"
         width="720" height="480" alt="Our courtyard" loading="lazy" decoding="async">
  </a>
</div>
<?php snippet('emaki') ?>
```

| Attribute | On | Meaning |
|---|---|---|
| `data-emaki` | container | One container is one sequence. Several containers are several independent sequences. |
| `href` | `<a>` | The large view. Use a rendered variant, never the original file. |
| `data-srcset` | `<a>` | Optional. Widths of the large view, for `srcset`. |
| `data-sizes` | `<a>` | Optional. `sizes` of the large view, default `100vw`. |
| `data-emaki-caption` | `<a>` | Optional caption. Present but empty means no caption. |
| `data-emaki-image` | `<a>` | Optional name of the image, passed to `emaki:open` as `image`. |

- A link takes part when its `href` ends in `.jpg`, `.jpeg`, `.png`, `.gif`, `.webp`, `.avif` or `.svg` (a query string is allowed), or when it has `data-srcset`. Other links in the container, such as a download link, are left alone.
- The caption is `data-emaki-caption` if the attribute is present, even when empty. Otherwise it is the `alt` text of the `<img>`. Otherwise there is no caption.
- Several containers on one page are several independent sequences. A container inside another one is its own sequence: a link belongs to the nearest container around it.

### Viewer behaviour

- **Opening.** A plain left click on a participating link opens the viewer at that image. Modified clicks (Ctrl, Shift, Cmd) and middle clicks keep their browser behaviour, and clicks that another script has already handled are respected.
- **Stepping.** The arrow buttons and the arrow keys step through the sequence and wrap around at both ends. `Home` and `End` jump to the first and the last image. On a touch screen, a horizontal swipe of at least 45 px steps.
- **Closing.** `Escape`, the close button, or a click anywhere except on the image, the caption and the buttons. Focus returns to the link the viewer was opened from, without scrolling the page.
- **Counter.** The counter shows the position, for example `3 / 7`. For a sequence of one image, the counter and both arrows are hidden.
- **Captions.** The caption is shown under the image. When it is shown, the dialog is described by it (`aria-describedby`) and the image's `alt` is empty, so the text is not read twice.
- **Accessible name.** The dialog is named after the position, for example "Image 3 of 7". The counter is `aria-live="polite"`.
- **Motion.** The open animation runs only for visitors who do not prefer reduced motion.
- If the page has no `data-emaki` container, or the browser has no `<dialog>`, Emaki does nothing and creates no element.

## Gallery and `$file->emaki()`

### The `emaki/gallery` snippet

```php
<?php snippet('emaki/gallery', [
    'files'   => $page->gallery()->toFiles(),
    'sizes'   => '(max-width: 40rem) 50vw, 20rem',
    'caption' => 'caption',
    'image'   => 'imagename',
]) ?>
```

The snippet takes these variables as keys of its data array:

| Variable | Default | Meaning |
|---|---|---|
| `files` | none | Files, or any iterable of files. Non-images are skipped. An image without thumbs is left out with an HTML comment. When no tile is left, no container is rendered, only the comments of the images left out (see [Images without thumbs](#images-without-thumbs)). |
| `sizes` | `'(max-width: 40rem) 100vw, 33vw'` | `sizes` of each tile's `<img>`. |
| `caption` | `'alt'` | `'alt'`: the alt text is the caption. A file field name, such as `'caption'`: that field's value. `false`: no caption. The tile gets `data-emaki-caption=""`, and its alt text is not used as a caption. |
| `class` | none | Extra classes on the container. |
| `tag` | `'div'` | The container element. |
| `attrs` | none | Extra attributes on the container. Only `data-*`, `aria-*` and `id` are kept; all others are dropped, and `data-emaki` is always Emaki's own. |
| `image` | none | A file field whose value becomes `data-emaki-image`, for counting. |

The output is one container with one tile per image. Each tile is a link to the large view with the attributes of the markup contract:

```html
<div class="emaki-gallery" data-emaki>
  <a class="emaki-item" href="…" data-srcset="…" data-sizes="100vw" data-emaki-caption="Our courtyard" data-emaki-image="courtyard">
    <img src="…" srcset="…" sizes="…" width="720" height="480" alt="Our courtyard" loading="lazy" decoding="async">
  </a>
</div>
```

The snippet adds no `<figure>` and no caption markup to the grid. The site decides how the tiles look. While `emaki.active` is not `true`, the same markup is rendered without `data-emaki`, so the links open the large image as plain links.

### `$file->emaki()`

For your own markup. `$file->emaki(array $options = [])` returns one flat array with keys for the `<a>` and for the `<img>`. The function `emaki_attributes($file, $options)` returns the same array. Both return `null` when no thumb can be created (the media folder is not writable, or a write fails), so test the result before you print anything; the gallery then leaves that image out and marks it in an HTML comment. See [Images without thumbs](#images-without-thumbs). The array does not contain the caption or the image name: add those yourself.

```php
<?php if ($e = $file->emaki(['sizes' => '(max-width: 40rem) 100vw, 33vw'])) : ?>
<div data-emaki>
  <a <?= attr([
      'href'               => $e['href'],
      'data-srcset'        => $e['data-srcset'],
      'data-sizes'         => $e['data-sizes'],
      'data-emaki-caption' => 'Terrace of Gasthaus Muster',
      'data-emaki-image'   => 'terrace',
  ]) ?>>
    <img <?= attr([
        'src'      => $e['src'],
        'srcset'   => $e['srcset'],
        'sizes'    => $e['sizes'],
        'width'    => $e['width'],
        'height'   => $e['height'],
        'alt'      => $e['alt'],
        'loading'  => $e['loading'],
        'decoding' => $e['decoding'],
    ]) ?>>
  </a>
</div>
<?php endif ?>
```

| Key | For | Value |
|---|---|---|
| `href` | `<a>` | The largest rendered large-view variant. Never the original file for a resizable image; a file Kirby cannot resize (SVG, a broken image) keeps its own URL. |
| `data-srcset` | `<a>` | The large-view `srcset`. `null` if the file is not resizable. |
| `data-sizes` | `<a>` | The global option `emaki.sizes`: the `sizes` of the large view. |
| `src` | `<img>` | The tile variant closest to, but not below, 720 px. If no variant is 720 px or wider, the largest tile variant. |
| `srcset` | `<img>` | The tile `srcset`. `null` if the file is not resizable. |
| `sizes` | `<img>` | The per-call `sizes`: the `sizes` of the tile. |
| `width`, `height` | `<img>` | Whole-pixel size of the `src` variant, with the orientation of the file applied. |
| `alt` | `<img>` | The file's alt text, or an empty string. |
| `loading` | `<img>` | `'lazy'`. |
| `decoding` | `<img>` | `'async'`. |

The options per call:

| Option | Default | Meaning |
|---|---|---|
| `sizes` | `'(max-width: 40rem) 100vw, 33vw'` | `sizes` of the tile, the `sizes` key of the `<img>`. Not the large view: that is the option `emaki.sizes`. |
| `large` | `emaki.srcset` | Preset name, or a list of widths, for the large view. |
| `tile` | `emaki.thumbnail` | Preset name, or a list of widths, for the tile. |
| `format` | `emaki.format` | Thumb format, such as `'webp'` or `'jpg'`. `null` (or leaving it out) uses `emaki.format`. If that is `null` too, Kirby's `thumbs.format` applies, and otherwise the file keeps its own format. |
| `quality` | `emaki.quality` | Thumb quality. `null` uses Kirby's `thumbs.quality`. |

A preset's entries contribute only their widths: a `quality` or `format` set on an entry of `thumbs.srcsets` is not applied. Use the options of the call, or `emaki.quality` and `emaki.format`.

Widths are never larger than the file itself. The largest width is always included, so a 1000 px image gets a large view at 1000 px. That is still a rendered variant, not the original file. Files that Kirby cannot resize, such as SVG images, and non-images get their own URL in `href` and `src`, `null` for both srcsets, and their own width and height, or `null`.

### Images without thumbs

When Kirby cannot create a variant (the media folder is not writable, or a write fails), `$file->emaki()` and `emaki_attributes()` return `null` and the gallery leaves that image out, with `<!-- emaki: no thumbs for <filename> -->` in its place. Emaki never falls back to the original file for a resizable image, because the original may carry metadata such as a GPS position.

### Cost

The first render of a page creates every variant that does not exist yet: by default up to eight per image, four large widths and four tile widths. Measured: about 1.6 s for one 24-megapixel image with a cold media folder. Kirby keeps the variants, so later renders cost nothing.

- After a deploy that empties the media folder, load the gallery pages once yourself (warm-up). Or configure fewer widths with `emaki.srcset` and `emaki.thumbnail`.

## Counting with `emaki:open`

Emaki fires `emaki:open` on the link that was opened, for every image shown: when the viewer opens and on each step. The event bubbles, so a listener on `document` gets all of them. Emaki sends nothing itself.

| `event.detail` | Type | Meaning |
|---|---|---|
| `href` | string | URL of the large view that is shown. |
| `image` | string or `null` | The link's `data-emaki-image`, or `null`. |
| `index` | integer | Position in the sequence, starting at 0. |
| `count` | integer | Number of images in the sequence. |

A listener on your site, about ten lines:

```js
document.addEventListener('emaki:open', (event) => {
  // Emaki sends nothing itself: this listener decides what to count.
  const { href, image, index, count } = event.detail;
  const body = JSON.stringify({ image: image ?? href, position: index + 1, of: count });
  navigator.sendBeacon('/emaki-count', body); // an endpoint you write
});
```

## Styling

Emaki styles only the viewer, the `.emaki` dialog and its parts. Set the three colour tokens on `:root` or on `.emaki`:

```css
:root {
  --emaki-bg: rgb(0 0 0 / 0.9);
  --emaki-fg: #ffffff;
  --emaki-accent: #e8c15a;
}
```

| Token | Default | Meaning |
|---|---|---|
| `--emaki-bg` | `rgb(12 16 20 / 0.88)` | The backdrop behind the image. |
| `--emaki-fg` | `#f4f5f3` | Text and button symbols. |
| `--emaki-accent` | `#ffffff` | The focus ring of the buttons and the current counter. |

The dialog's structure, with the classes and attributes the stylesheet relies on:

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

- `.emaki--motion` is set on the dialog unless the visitor prefers reduced motion. The open animation runs only with this class.
- `.is-loading` is set on the dialog while an image loads. The image box then shows a subtle placeholder.
- The buttons are at least 48 px. Hidden parts stay hidden: the `hidden` attribute is honoured.
- The stylesheet has no rules for `.emaki-gallery` or `.emaki-item`. Style those yourself.
- The stylesheet loads nothing from elsewhere: no `@import`, no external URLs.

## Configuration

Every option is read as `emaki.<name>`, either flat (`'emaki.active' => true`) or nested (`'emaki' => ['active' => true]`). `null` counts as "not set".

| Option | Default | Meaning |
|---|---|---|
| `active` | `false` | Master switch. Only `true` switches Emaki on. While off, the `emaki` snippet prints nothing, `emaki/gallery` renders the same markup without `data-emaki`, and `$file->emaki()` still returns its data. |
| `language` | request language, then default language, then `locale`, then `en` | Language of the labels. English and German are shipped. |
| `texts` | none | Overrides of the labels, per key, flat or per language. See [Labels](#labels). |
| `script` | `true` | `false` leaves the `<script>` out of `emaki_tags()`, for sites that bundle `emaki.js` themselves. Such a site renders the label attributes on its own script tag: `data-dialog`, `data-close`, `data-prev`, `data-next` and `data-counter`, one for each key of the [Labels](#labels) table. `emaki_tags()` is the only code that writes them. |
| `style` | `true` | `false` leaves the `<link>` out of `emaki_tags()`, for sites that bundle `emaki.css` themselves. |
| `srcset` | `'emaki'` | Large view: a preset name in `thumbs.srcsets`, or a list of widths. Without the preset: 800, 1200, 1800, 2400. |
| `thumbnail` | `'emaki-tile'` | Tile: a preset name in `thumbs.srcsets`, or a list of widths. Without the preset: 320, 480, 720, 960. |
| `sizes` | `'100vw'` | `sizes` of the large view, written as `data-sizes`. The per-call `sizes` of `$file->emaki()` and of `emaki/gallery` is the tile's `sizes` instead: same word, different scope. |
| `format` | `null` | Thumb format for both views, such as `'webp'` or `'jpg'`. `null` keeps the file's format, unless Kirby's `thumbs.format` is set; Kirby then applies that. |
| `quality` | `null` | Thumb quality for both views. `null` uses Kirby's `thumbs.quality`. |

### Labels

| Key | Default (English) | Used for |
|---|---|---|
| `dialog` | `Image {n} of {count}` | The accessible name of the dialog. |
| `counter` | `{n} / {count}` | The visible counter. |
| `button.close` | `Close` | The name of the close button. |
| `button.prev` | `Previous image` | The name of the previous button. |
| `button.next` | `Next image` | The name of the next button. |

`{n}` is the 1-based position, `{count}` the number of images. Override a label with `emaki.texts`, either for every language:

```php
'emaki.texts' => ['button.close' => 'Done'],
```

or for one language only:

```php
'emaki.texts' => ['de' => ['button.close' => 'Schließen']],
```

The labels are also registered as Kirby translations under `emaki.<key>`.

### Functions

| Function | Returns |
|---|---|
| `emaki_tags()` | The `<link>` and `<script>` tags of the viewer, once per page. The script carries the labels as `data-*` attributes (`data-dialog`, `data-close`, `data-prev`, `data-next`, `data-counter`). Empty while Emaki is off. |
| `emaki_attributes(File $file, array $options = [])` | The same array as `$file->emaki()`. |
| `emaki_asset_url(string $file)` | The URL of a file that Emaki serves, such as `emaki.js` or `emaki.css`: `/media/plugins/sayamaapps/emaki/<hash>-<time>/<file>` on your site, without a query string. |
| `emaki_option(string $key, mixed $default = null)` | The value of `emaki.<key>`, or the default. |
| `emaki_i18n()` | Sets the language and the label overrides for the request. `emaki_tags()` calls it. |

## Privacy

Emaki sets no cookies, uses no browser storage and sends nothing. The viewer's script and stylesheet come from your own server. The images are the ones your markup links: the image that is opened, and its two neighbours, which the viewer fetches in advance. If the markup links an image on another host, the browser fetches it from there. The `emaki:open` event stays in the page until your own code acts on it. [PRIVACY.md](PRIVACY.md) describes the implementation in English, and [DATENSCHUTZ.md](DATENSCHUTZ.md) holds the German text for a site's privacy policy.

## Maintenance

Emaki is maintained for our own projects. Issues are welcome, but there is no support promise.

## Licence

MIT. See [LICENSE](LICENSE).
