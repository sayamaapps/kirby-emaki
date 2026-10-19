<?php

namespace Emaki;

use Kirby\Cms\App;

/**
 * Reads `emaki.*` options.
 *
 * `null` counts as "not set", like Kirby's own option(), so
 * `'emaki.format' => null` falls back to the default. Both spellings work,
 * flat (`'emaki.active' => true`) and nested (`'emaki' => ['active' => true]`).
 *
 * Defaults are passed at each call, NOT in an 'options' block of the plugin
 * (Kirby would file them under sayamaapps.emaki.* otherwise).
 */
final class Options
{
    public const PREFIX = 'emaki.';

    /** Widths for the large view when no `thumbs.srcsets` preset is configured; never above the file's width. */
    public const LARGE_WIDTHS = [800, 1200, 1800, 2400];

    /** Widths for the tile when no preset is configured; never above the file's width. */
    public const TILE_WIDTHS = [320, 480, 720, 960];

    public static function get(App $kirby, string $key, mixed $default = null): mixed
    {
        return $kirby->option(self::PREFIX . $key) ?? $default;
    }

    /** Master switch. Only `true` switches on. */
    public static function active(App $kirby): bool
    {
        return self::get($kirby, 'active', false) === true;
    }

    /**
     * Language of the labels: `emaki.language`, otherwise the language of the
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
