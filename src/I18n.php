<?php

namespace Emaki;

/**
 * Visitor-facing strings, Kirby-free.
 *
 * The tables live in translations/<language>.php and are also registered as
 * Kirby plugin translations under `emaki.<key>` (index.php). Emaki reads its
 * own tables: a missing Kirby translation must never silently turn a German
 * label English.
 *
 * `emaki.texts` replaces single strings, per language or for all:
 *
 *   'emaki.texts' => ['de' => ['button.close' => 'Zumachen']]
 *   'emaki.texts' => ['button.close' => 'Close']
 *
 * The language is set once per request (index.php, Options::language()) and
 * defaults to English. Missing keys fall back to English, then to the key.
 */
final class I18n
{
    /** Languages Emaki ships. English is the default and the fallback. */
    public const LANGUAGES = ['en', 'de'];

    private static string $language = 'en';

    /** @var array<string, array<string,string>> */
    private static array $tables = [];

    /** @var array<string,string> */
    private static array $overrides = [];

    public static function setLanguage(string $language): void
    {
        self::$language = in_array($language, self::LANGUAGES, true) ? $language : 'en';
    }

    public static function language(): string
    {
        return self::$language;
    }

    /** Strings from `emaki.texts` for the current language; flat keys apply to every language. */
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

    /** @return array<string,string> */
    public static function table(string $language): array
    {
        if (!in_array($language, self::LANGUAGES, true)) {
            return [];
        }
        return self::$tables[$language] ??= require dirname(__DIR__) . '/translations/' . $language . '.php';
    }

    /** `{name}` placeholders are replaced with the given values, unescaped. */
    public static function t(string $key, array $values = []): string
    {
        $text = self::$overrides[$key] ?? self::table(self::$language)[$key] ?? self::table('en')[$key] ?? $key;
        foreach ($values as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }

    /**
     * The labels the script needs to build the dialog, for the data
     * attributes of the <script> tag (emaki_tags()). Keys are the attribute
     * names without the `data-` prefix.
     *
     * @return array<string,string>
     */
    public static function labels(): array
    {
        return [
            'dialog'  => self::t('dialog'),
            'close'   => self::t('button.close'),
            'prev'    => self::t('button.prev'),
            'next'    => self::t('button.next'),
            'counter' => self::t('counter'),
        ];
    }
}
