<?php

use Kirby\Cms\App;
use Kirby\Cms\File;

require_once __DIR__ . '/src/I18n.php';
require_once __DIR__ . '/src/Options.php';
require_once __DIR__ . '/src/Presets.php';
require_once __DIR__ . '/src/Images.php';

use Emaki\I18n;
use Emaki\Images;
use Emaki\Options;

/**
 * Emaki (絵巻, the picture scroll: one image after the other, unrolled by
 * hand) — an image viewer for galleries, served from the site's own server.
 * A <dialog> built by assets/emaki.js, styled by assets/emaki.css, attached
 * to every container marked `data-emaki`. No library, no CDN, no inline code,
 * no cookies, no requests to anyone but the site itself.
 *
 * Off by default: without `'emaki.active' => true` the `emaki` snippet prints
 * nothing, `emaki/gallery` renders plain links to the large image (the
 * browser opens the file), and `$file->emaki()` still returns its
 * attributes, because they are plain data.
 *
 * Defaults are passed as fallback values directly at each option call, NOT
 * in an 'options' block (Kirby would file them under sayamaapps.emaki.active
 * otherwise).
 */

/** Reads `emaki.<key>`. Also meant for site snippets with their own markup. */
function emaki_option(string $key, mixed $default = null): mixed
{
    return Options::get(App::instance(), $key, $default);
}

/** Language and `emaki.texts` for this request. Snippets call it before printing text. */
function emaki_i18n(): void
{
    $kirby = App::instance();
    I18n::setLanguage(Options::language($kirby));
    I18n::setOverrides(Options::get($kirby, 'texts'));
}

/** Address of a file in assets/: /media/plugins/sayamaapps/emaki/<hash>-<time>/<file>, no query string. */
function emaki_asset_url(string $file): ?string
{
    return App::instance()->plugin('sayamaapps/emaki')?->asset($file)?->url();
}

/**
 * The <link> for emaki.css and the <script> for emaki.js, once per page: the
 * first call prints them, later ones print nothing. Both external and the
 * script deferred, so a Content Security Policy needs no 'unsafe-inline'.
 * The labels the script needs travel as data attributes of the script tag.
 * `'emaki.style' => false` / `'emaki.script' => false` leave out the tag for
 * sites that bundle the file themselves. Emaki off: nothing at all.
 */
function emaki_tags(): string
{
    static $printed = null;
    $printed ??= new WeakMap();
    $kirby = App::instance();
    if (isset($printed[$kirby]) || !Options::active($kirby)) {
        return '';
    }
    $printed[$kirby] = true;
    emaki_i18n();

    $html = '';
    if (emaki_option('style', true) !== false && ($css = emaki_asset_url('emaki.css')) !== null) {
        $html .= '<link rel="stylesheet" href="' . esc($css) . '">';
    }
    if (emaki_option('script', true) !== false && ($js = emaki_asset_url('emaki.js')) !== null) {
        $data = '';
        foreach (I18n::labels() as $name => $label) {
            $data .= ' data-' . $name . '="' . esc($label) . '"';
        }
        $html .= '<script src="' . esc($js) . '" defer' . $data . '></script>';
    }
    return $html;
}

/**
 * Attributes for a gallery tile and its large view (`$file->emaki()` behind
 * the scenes): keys for the <img> (src, srcset, sizes, width, height, alt,
 * loading, decoding) and for the <a> (href = the largest rendered variant,
 * never the original; data-srcset, data-sizes). For sites that build their
 * own markup. Options per call: sizes, large, tile, format, quality — see
 * the README. Null when Kirby cannot create the thumbs (media folder not
 * writable, or a write fails); the gallery then leaves that image out.
 */
function emaki_attributes(File $file, array $options = []): ?array
{
    return Images::attributes($file, $options);
}

$emakiTranslations = [];
foreach (I18n::LANGUAGES as $emakiLanguage) {
    foreach (I18n::table($emakiLanguage) as $emakiKey => $emakiText) {
        $emakiTranslations[$emakiLanguage]['emaki.' . $emakiKey] = $emakiText;
    }
}
unset($emakiLanguage, $emakiKey, $emakiText);

App::plugin('sayamaapps/emaki', [
    // The labels, also usable as t('emaki.<key>').
    'translations' => $emakiTranslations,

    /**
     * `emaki` prints the stylesheet and script tags (once); `emaki/gallery`
     * renders a container with one tile per file. A site with its own
     * markup creates site/snippets/emaki/gallery.php — Kirby looks there first.
     */
    'snippets' => [
        'emaki'         => __DIR__ . '/snippets/emaki.php',
        'emaki/gallery' => __DIR__ . '/snippets/emaki/gallery.php',
    ],

    /** `$file->emaki([...])`, see emaki_attributes(). */
    'fileMethods' => [
        'emaki' => function (array $options = []): ?array {
            return Images::attributes($this, $options);
        },
    ],
]);
