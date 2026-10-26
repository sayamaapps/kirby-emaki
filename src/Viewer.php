<?php

namespace Sayamaapps\Emaki;

use Kirby\Cms\App;
use WeakMap;

/**
 * The viewer's tags and the request setup: what the `emaki` snippet prints and
 * what `emaki/gallery` needs before it prints a label.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-emaki
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class Viewer
{
	/**
	 * Address of a file in assets/: /media/plugins/sayamaapps/emaki/<hash>-<time>/<file>, no query string.
	 */
	public static function assetUrl(App $kirby, string $file): string|null
	{
		return $kirby->plugin('sayamaapps/emaki')?->asset($file)?->url();
	}

	/**
	 * Sets the language and the label overrides for this request. Called before
	 * any label is printed.
	 */
	public static function prepare(App $kirby): void
	{
		I18n::setLanguage(Options::language($kirby));
		I18n::setOverrides(Options::get($kirby, 'texts'));
	}

	/**
	 * The <link> for emaki.css and the <script> for emaki.js, once per page: the
	 * first call prints them, later ones print nothing. Both external and the
	 * script deferred, so a Content Security Policy needs no 'unsafe-inline'.
	 * The labels the script needs travel as data attributes of the script tag.
	 * `'sayamaapps.emaki.style' => false` / `'sayamaapps.emaki.script' => false`
	 * leave out the tag for sites that bundle the file themselves. Emaki off:
	 * nothing at all.
	 */
	public static function tags(App $kirby): string
	{
		static $printed = null;
		$printed ??= new WeakMap();
		if (isset($printed[$kirby]) || !Options::active($kirby)) {
			return '';
		}
		$printed[$kirby] = true;
		self::prepare($kirby);

		$html = '';
		if (Options::get($kirby, 'style', true) !== false && ($stylesheet = self::assetUrl($kirby, 'emaki.css')) !== null) {
			$html .= '<link rel="stylesheet" href="' . esc($stylesheet) . '">';
		}
		if (Options::get($kirby, 'script', true) !== false && ($script = self::assetUrl($kirby, 'emaki.js')) !== null) {
			$data = '';
			foreach (I18n::labels() as $name => $label) {
				$data .= ' data-' . $name . '="' . esc($label) . '"';
			}
			$html .= '<script src="' . esc($script) . '" defer' . $data . '></script>';
		}
		return $html;
	}
}
