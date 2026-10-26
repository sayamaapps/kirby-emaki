<?php

namespace Sayamaapps\Emaki;

use Kirby\Toolkit\I18n as KirbyI18n;

/**
 * Visitor-facing labels of the viewer.
 *
 * The strings are Kirby plugin translations under `sayamaapps.emaki.<key>`
 * (config/translations.php, translations/<language>.php). This class reads them
 * with Kirby's own translation lookup, in the language set for the request.
 * A key missing in that language falls back to English through Kirby, then
 * to the key itself.
 *
 * `sayamaapps.emaki.texts` replaces single strings, per language or for all:
 *
 *   'sayamaapps.emaki.texts' => ['de' => ['button.close' => 'Zumachen']]
 *   'sayamaapps.emaki.texts' => ['button.close' => 'Close']
 *
 * The language is set once per request (Viewer::prepare(), Options::language())
 * and defaults to English.
 *
 * @package   Kirby Emaki
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-emaki
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class I18n
{
	/** Languages Emaki ships. English is the default and the fallback. */
	public const LANGUAGES = ['en', 'de'];

	private static string $language = 'en';

	/** @var array<string,string> */
	private static array $overrides = [];

	/**
	 * One label: the override from `texts`, else the Kirby translation in the current language.
	 */
	private static function label(string $key): string
	{
		if (isset(self::$overrides[$key])) {
			return self::$overrides[$key];
		}

		$text = KirbyI18n::translate('sayamaapps.emaki.' . $key, null, self::$language);
		return is_string($text) ? $text : $key;
	}

	/**
	 * The labels the script needs to build the dialog, for the data
	 * attributes of the <script> tag (Viewer::tags()). Keys are the attribute
	 * names without the `data-` prefix. Placeholders such as `{n}` stay in
	 * the text: the script fills them.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array
	{
		return [
			'dialog'  => self::label('dialog'),
			'close'   => self::label('button.close'),
			'prev'    => self::label('button.prev'),
			'next'    => self::label('button.next'),
			'counter' => self::label('counter'),
		];
	}

	/**
	 * The current language of the labels.
	 */
	public static function language(): string
	{
		return self::$language;
	}

	/**
	 * Sets the language of the labels. Unknown languages count as English.
	 */
	public static function setLanguage(string $language): void
	{
		self::$language = in_array($language, self::LANGUAGES, true) ? $language : 'en';
	}

	/**
	 * Strings from `sayamaapps.emaki.texts` for the current language; flat keys apply to every language.
	 */
	public static function setOverrides(mixed $texts): void
	{
		self::$overrides = [];
		if (!is_array($texts)) {
			return;
		}
		$perLanguage = is_array($texts[self::$language] ?? null) ? $texts[self::$language] : [];
		foreach ([$texts, $perLanguage] as $table) {
			foreach ($table as $key => $text) {
				if (is_string($key) && is_string($text)) {
					self::$overrides[$key] = $text;
				}
			}
		}
	}
}
