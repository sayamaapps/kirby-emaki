<?php

/**
 * Names of the 1.x API, kept in 1.x and removed in 2.0 (README, Deprecated).
 *
 * Loaded once by index.php, after the class map is registered. Class aliases and
 * function wrappers only; no logic here, and no runtime deprecation: the
 * deprecation is in the docblocks and the README.
 */

class_alias(\Sayamaapps\Emaki\Images::class, 'Emaki\\Images');
class_alias(\Sayamaapps\Emaki\I18n::class, 'Emaki\\I18n');
class_alias(\Sayamaapps\Emaki\Options::class, 'Emaki\\Options');
class_alias(\Sayamaapps\Emaki\Presets::class, 'Emaki\\Presets');

if (Kirby\Cms\Helpers::hasOverride('emaki_option') === false) {
	/**
	 * Reads `emaki.<key>`.
	 *
	 * @deprecated Use Sayamaapps\Emaki\Options::get().
	 */
	function emaki_option(string $key, mixed $default = null): mixed
	{
		return Sayamaapps\Emaki\Options::get(Kirby\Cms\App::instance(), $key, $default);
	}
}

if (Kirby\Cms\Helpers::hasOverride('emaki_i18n') === false) {
	/**
	 * Sets the language and the label overrides.
	 *
	 * @deprecated Use Sayamaapps\Emaki\Viewer::prepare().
	 */
	function emaki_i18n(): void
	{
		Sayamaapps\Emaki\Viewer::prepare(Kirby\Cms\App::instance());
	}
}

if (Kirby\Cms\Helpers::hasOverride('emaki_asset_url') === false) {
	/**
	 * URL of a file in assets/.
	 *
	 * @deprecated Use Sayamaapps\Emaki\Viewer::assetUrl().
	 */
	function emaki_asset_url(string $file): string|null
	{
		return Sayamaapps\Emaki\Viewer::assetUrl(Kirby\Cms\App::instance(), $file);
	}
}

if (Kirby\Cms\Helpers::hasOverride('emaki_tags') === false) {
	/**
	 * The viewer's tags.
	 *
	 * @deprecated Use Sayamaapps\Emaki\Viewer::tags().
	 */
	function emaki_tags(): string
	{
		return Sayamaapps\Emaki\Viewer::tags(Kirby\Cms\App::instance());
	}
}

if (Kirby\Cms\Helpers::hasOverride('emaki_attributes') === false) {
	/**
	 * Attributes of a tile and its large view.
	 *
	 * @deprecated Use Sayamaapps\Emaki\Images::attributes().
	 */
	function emaki_attributes(Kirby\Cms\File $file, array $options = []): array|null
	{
		return Sayamaapps\Emaki\Images::attributes($file, $options);
	}
}
