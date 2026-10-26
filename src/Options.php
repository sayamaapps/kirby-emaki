<?php

namespace Sayamaapps\Emaki;

use Kirby\Cms\App;
use Kirby\Cms\Helpers;

/**
 * Reads Emaki's options: `sayamaapps.emaki.<key>`, falling back to the older
 * name `emaki.<key>` (see the README, Deprecations).
 *
 * `null` counts as "not set", like Kirby's own option(), so
 * `'sayamaapps.emaki.format' => null` falls back to the default. Both spellings
 * work, flat (`'sayamaapps.emaki.active' => true`) and nested
 * (`'sayamaapps' => ['emaki' => ['active' => true]]`).
 *
 * Defaults are given at each call. The `options` block of the plugin lists the
 * keys with null: a default there would hide the older spelling.
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

	/** Option prefix of the plugin. */
	public const OPTION_PREFIX = 'sayamaapps.emaki.';

	/** Option prefix of 1.x, read as a fallback until the next major version. Keeps its 1.0 value. */
	public const PREFIX = 'emaki.';

	/** Widths for the tile when no preset is configured; never above the file's width. */
	public const TILE_WIDTHS = [320, 480, 720, 960];

	/**
	 * Master switch. Only `true` switches on.
	 */
	public static function active(App $kirby): bool
	{
		return self::get($kirby, 'active', false) === true;
	}

	/**
	 * Reads an option. The new name wins; the older `emaki.<key>` is read
	 * when the new one is not set, with a deprecation in debug mode.
	 */
	public static function get(App $kirby, string $key, mixed $default = null): mixed
	{
		$value = $kirby->option(self::OPTION_PREFIX . $key);
		if ($value !== null) {
			return $value;
		}

		// The older spelling still works, with a deprecation (README, Deprecations).
		$legacy = $kirby->option(self::PREFIX . $key);
		if ($legacy !== null) {
			Helpers::deprecated(
				'Emaki: emaki.' . $key . ' is deprecated, use ' . self::OPTION_PREFIX . $key,
				'option-prefix'
			);
		}

		return $legacy ?? $default;
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
