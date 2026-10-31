<?php

namespace Sayamaapps\Emaki;

use Kirby\Cms\App;

/**
 * Reads Emaki's options: `sayamaapps.emaki.<key>`, falling back to the 1.x name
 * `emaki.<key>`.
 *
 * The flat form (`'sayamaapps.emaki.active' => true`) is the one to use. The
 * nested form (`'emaki' => ['active' => true]`) works for the 1.x prefix only:
 * Kirby resolves `sayamaapps.emaki.*` through the plugin's own options block
 * first, which shadows a nested `sayamaapps` array.
 *
 * `null` counts as "not set", like Kirby's own option(), so
 * `'sayamaapps.emaki.format' => null` falls back to the default.
 *
 * Defaults are passed at each call, NOT in an 'options' block of the plugin
 * (Kirby would file them under the plugin's own name otherwise). The `options`
 * block in config/options.php lists the keys with null: a default there would
 * hide the 1.x spelling.
 *
 * @package   Kirby Emaki
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-emaki
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Options
{
	/** Widths for the large view when no `thumbs.srcsets` preset is configured; never above the file's width. */
	public const LARGE_WIDTHS = [800, 1200, 1800, 2400];

	/** Option prefix since 1.2. */
	public const OPTION_PREFIX = 'sayamaapps.emaki.';

	/**
	 * Option prefix of 1.x (`emaki.<key>`). Still read as a fallback until 2.0.
	 *
	 * @deprecated 1.2 use OPTION_PREFIX; removal planned for 2.0
	 */
	public const PREFIX = 'emaki.';

	/** Widths for the tile when no preset is configured; never above the file's width. */
	public const TILE_WIDTHS = [320, 480, 720, 960];

	/** Master switch. Only `true` switches on. */
	public static function active(App $kirby): bool
	{
		return self::get($kirby, 'active', false) === true;
	}

	/**
	 * Reads an option under the sayamaapps.emaki.* spelling, then under the
	 * 1.x emaki.* spelling, which is read silently (README, Deprecated and planned
	 * for 2.0). Returns $default when neither is set.
	 */
	public static function get(App $kirby, string $key, mixed $default = null): mixed
	{
		$value = $kirby->option(self::OPTION_PREFIX . $key);
		if ($value !== null) {
			return $value;
		}

		return $kirby->option(self::PREFIX . $key) ?? $default;
	}

	/**
	 * Language of the labels: `sayamaapps.emaki.language`, otherwise the language of the
	 * current request (multi-language sites), the default language, the first
	 * two letters of Kirby's `locale` option, English. Only languages Emaki
	 * ships (I18n::LANGUAGES) are returned.
	 */
	public static function language(App $kirby): string
	{
		$candidates = [self::get($kirby, 'language')];
		try {
			$candidates[] = $kirby->language()?->code();
			$candidates[] = $kirby->defaultLanguage()?->code();
		} catch (\Throwable) {
			// No languages configured.
		}
		$locale = $kirby->option('locale');
		if (is_array($locale)) {
			$locale = $locale[LC_ALL] ?? reset($locale);
		}
		$candidates[] = is_string($locale) ? substr($locale, 0, 2) : null;

		foreach ($candidates as $candidate) {
			if (is_string($candidate) && in_array(strtolower(substr($candidate, 0, 2)), I18n::LANGUAGES, true)) {
				return strtolower(substr($candidate, 0, 2));
			}
		}
		return 'en';
	}
}
