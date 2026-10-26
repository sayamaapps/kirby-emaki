<?php

/**
 * Names of the 1.x API, kept until 2.0 (README, Deprecations).
 *
 * Loaded once by index.php, after the class map is registered. Class aliases and
 * function wrappers only; no logic here.
 */

class_alias(\Sayamaapps\Emaki\Images::class, 'Emaki\\Images');
class_alias(\Sayamaapps\Emaki\I18n::class, 'Emaki\\I18n');
class_alias(\Sayamaapps\Emaki\Options::class, 'Emaki\\Options');
class_alias(\Sayamaapps\Emaki\Presets::class, 'Emaki\\Presets');

if (Kirby\Cms\Helpers::hasOverride('emaki_option') === false) {
	/** Reads `emaki.<key>`. Deprecated: use Sayamaapps\Emaki\Options::get(). */
	function emaki_option(string $key, mixed $default = null): mixed
	{
		Kirby\Cms\Helpers::deprecated(
			'emaki_option() is deprecated, use Sayamaapps\Emaki\Options::get()',
			'emaki-function'
		);

		return Sayamaapps\Emaki\Options::get(Kirby\Cms\App::instance(), $key, $default);
	}
}

if (Kirby\Cms\Helpers::hasOverride('emaki_i18n') === false) {
	/** Sets the language and label overrides. Deprecated: use Sayamaapps\Emaki\Viewer::prepare(). */
	function emaki_i18n(): void
	{
		Kirby\Cms\Helpers::deprecated(
			'emaki_i18n() is deprecated, use Sayamaapps\Emaki\Viewer::prepare()',
			'emaki-function'
		);

		Sayamaapps\Emaki\Viewer::prepare(Kirby\Cms\App::instance());
	}
}

if (Kirby\Cms\Helpers::hasOverride('emaki_asset_url') === false) {
	/** URL of a file in assets/. Deprecated: use Sayamaapps\Emaki\Viewer::assetUrl(). */
	function emaki_asset_url(string $file): string|null
	{
		Kirby\Cms\Helpers::deprecated(
			'emaki_asset_url() is deprecated, use Sayamaapps\Emaki\Viewer::assetUrl()',
			'emaki-function'
		);

		return Sayamaapps\Emaki\Viewer::assetUrl(Kirby\Cms\App::instance(), $file);
	}
}

if (Kirby\Cms\Helpers::hasOverride('emaki_tags') === false) {
	/** The viewer's tags. Deprecated: use Sayamaapps\Emaki\Viewer::tags(). */
	function emaki_tags(): string
	{
		Kirby\Cms\Helpers::deprecated(
			'emaki_tags() is deprecated, use Sayamaapps\Emaki\Viewer::tags()',
			'emaki-function'
		);

		return Sayamaapps\Emaki\Viewer::tags(Kirby\Cms\App::instance());
	}
}

if (Kirby\Cms\Helpers::hasOverride('emaki_attributes') === false) {
	/** Attributes of a tile and its large view. Deprecated: use Sayamaapps\Emaki\Images::attributes(). */
	function emaki_attributes(Kirby\Cms\File $file, array $options = []): array|null
	{
		Kirby\Cms\Helpers::deprecated(
			'emaki_attributes() is deprecated, use Sayamaapps\Emaki\Images::attributes()',
			'emaki-function'
		);

		return Sayamaapps\Emaki\Images::attributes($file, $options);
	}
}
