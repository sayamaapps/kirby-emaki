<?php

namespace Emaki;

use Kirby\Cms\App;

/**
 * Turns a `thumbs.srcsets` preset name (or a plain list of widths) into the
 * widths Emaki renders for one file (SPEC.md §2.4).
 */
final class Presets
{
    /**
     * Widths to render, ascending and unique, all below $fileWidth, plus
     * min($fileWidth, largest width): a small file still gets its one variant
     * and a large file never gets a variant above its own width.
     *
     * `$preset` is a preset name in `thumbs.srcsets.<name>`, a list of ints,
     * or null. A preset that is not configured at all (null from Kirby) uses
     * `$fallback` as its list. A configured list without a single usable width
     * falls back to the single width [min($fileWidth, max($fallback))].
     *
     * Files narrower than 1 px have no widths at all.
     *
     * Only the width of an entry counts. Other options of an entry (`quality`,
     * `format`) are not applied; the options of the call are.
     *
     * @param string|array<int|string, mixed>|null $preset
     * @param int[] $fallback
     * @return int[]
     */
    public static function widths(App $kirby, string|array|null $preset, array $fallback, int $fileWidth): array
    {
        if ($fileWidth < 1) {
            return [];
        }

        $configured = is_string($preset) ? $kirby->option('thumbs.srcsets.' . $preset) : $preset;
        $declared   = self::declared($configured ?? $fallback);

        if ($declared === []) {
            $fallback = self::declared($fallback);
            return [$fallback === [] ? $fileWidth : min($fileWidth, max($fallback))];
        }

        $widths   = array_filter($declared, fn (int $width): bool => $width < $fileWidth);
        $widths[] = min($fileWidth, max($declared));
        $widths   = array_values(array_unique($widths));
        sort($widths);

        return $widths;
    }

    /**
     * The usable widths of one list, in Kirby's spellings:
     *  - a plain list of widths: `[320, 640]` or `['320', '640']`
     *  - `width => 'condition'`: `[320 => 'landscape']`, the key is the width
     *  - `'descriptor' => ['width' => 320, …]`: the option `width` is the width
     *    (an integer key is taken when that option is missing)
     * Entries that are not numeric or not above 0 are ignored, and so are the
     * other options of an entry: only its width counts.
     *
     * @return int[] ascending, unique
     */
    private static function declared(mixed $list): array
    {
        if (!is_array($list)) {
            return [];
        }

        $widths = [];
        foreach ($list as $key => $value) {
            $width = match (true) {
                is_array($value)   => $value['width'] ?? (is_int($key) ? $key : null),
                is_numeric($value) => $value,
                is_string($value)  => is_int($key) ? $key : null,
                default            => null,
            };
            if (is_numeric($width) && (int) $width > 0) {
                $widths[] = (int) $width;
            }
        }

        $widths = array_values(array_unique($widths));
        sort($widths);
        return $widths;
    }
}
