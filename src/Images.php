<?php

namespace Emaki;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Image\Image;

/**
 * The attributes for a tile and its large view, all from Kirby thumbs
 * (SPEC.md §2.3). The original file is never the `href`, never upscaled and
 * never written to.
 */
final class Images
{
	/** The tile `src` is the smallest tile variant at least this wide (px), else the largest. */
	private const SRC_FROM = 720;

	/** Default `sizes` of the tile (per call); the large view uses the option `emaki.sizes`. */
	private const TILE_SIZES = '(max-width: 40rem) 100vw, 33vw';

	/**
	 * Returns null when a thumb cannot be created (media folder not writable,
	 * or a write fails): Kirby then hands back the file itself, whose URL would
	 * be the original. Nothing is linked in that case.
	 *
	 * `format` null means "not set per call": `emaki.format` applies; if that
	 * is not set either, Kirby's `thumbs.format` applies, and otherwise the
	 * file keeps its own format.
	 *
	 * @param array{sizes?: string, large?: string|int[]|null, tile?: string|int[]|null, format?: string|null, quality?: int|null} $options
	 * @return array<string, string|int|null>|null keys: href, data-srcset, data-sizes, src, srcset, sizes, width, height, alt, loading, decoding
	 */
	public static function attributes(File $file, array $options = []): array|null
	{
		$kirby = App::instance();
		$sizes = is_string($options['sizes'] ?? null) ? $options['sizes'] : self::TILE_SIZES;
		$alt   = $file->alt()->value();
		$alt   = is_string($alt) ? $alt : '';

		if (!$file->isResizable()) {
			return self::plain($file, $kirby, $sizes, $alt);
		}

		$format  = $options['format'] ?? Options::get($kirby, 'format');
		$quality = $options['quality'] ?? Options::get($kirby, 'quality');
		$thumb   = function (int $width) use ($file, $format, $quality): FileVersion|null {
			$thumbOptions = ['width' => $width, 'autoOrient' => true];
			if (is_string($format) && $format !== '') {
				$thumbOptions['format'] = $format;
			}
			if (is_numeric($quality)) {
				$thumbOptions['quality'] = (int)$quality;
			}
			$version = $file->thumb($thumbOptions);
			// Kirby hands back the file itself when it cannot queue the thumb (media folder not writable).
			if ($version instanceof FileVersion === false) {
				return null;
			}
			return self::store($version) ? $version : null;
		};

		// Large view: the widths of the preset, the variant at the file's own width last.
		$fileWidth  = $file->width();
		$large      = Presets::widths(
			$kirby,
			self::preset($options['large'] ?? Options::get($kirby, 'srcset', 'emaki')),
			Options::LARGE_WIDTHS,
			$fileWidth
		);
		$largeThumbs = self::variants($large, $thumb);
		if ($largeThumbs === null) {
			return null;
		}

		// Tile: all widths for the srcset, `src` the one at or just above 720 px.
		$tile = Presets::widths(
			$kirby,
			self::preset($options['tile'] ?? Options::get($kirby, 'thumbnail', 'emaki-tile')),
			Options::TILE_WIDTHS,
			$fileWidth
		);
		$tileThumbs = self::variants($tile, $thumb);
		if ($tileThumbs === null) {
			return null;
		}
		$srcWidth = max($tile);
		foreach ($tile as $width) {
			if ($width >= self::SRC_FROM) {
				$srcWidth = $width;
				break;
			}
		}
		$srcThumb = $tileThumbs[$srcWidth];

		return [
			'href'        => $largeThumbs[max($large)]->url(),
			'data-srcset' => implode(', ', array_map(
				fn (int $width): string => $largeThumbs[$width]->url() . ' ' . $width . 'w',
				$large
			)),
			'data-sizes'  => self::text(Options::get($kirby, 'sizes', '100vw')),
			'src'         => $srcThumb->url(),
			'srcset'      => implode(', ', array_map(
				fn (int $width): string => $tileThumbs[$width]->url() . ' ' . $width . 'w',
				$tile
			)),
			'sizes'    => $sizes,
			'width'    => $srcThumb->width(),
			'height'   => $srcThumb->height(),
			'alt'      => $alt,
			'loading'  => 'lazy',
			'decoding' => 'async',
		];
	}

	/**
	 * A file without thumbs (SVG, documents, broken images): the file itself,
	 * no srcsets, its own size when it has one.
	 *
	 * @return array<string, string|int|null>
	 */
	private static function plain(File $file, App $kirby, string $sizes, string $alt): array
	{
		// Only an image has a size; a PDF's width() would be a content field.
		$asset  = $file->asset();
		$width  = $asset instanceof Image ? $asset->width() : 0;
		$height = $asset instanceof Image ? $asset->height() : 0;

		return [
			'href'        => $file->url(),
			'data-srcset' => null,
			'data-sizes'  => self::text(Options::get($kirby, 'sizes', '100vw')),
			'src'         => $file->url(),
			'srcset'      => null,
			'sizes'       => $sizes,
			'width'       => $width > 0 ? $width : null,
			'height'      => $height > 0 ? $height : null,
			'alt'         => $alt,
			'loading'     => 'lazy',
			'decoding'    => 'async',
		];
	}

	/** A preset name or a list of widths; anything else counts as "not set". */
	private static function preset(mixed $value): string|array|null
	{
		return is_string($value) || is_array($value) ? $value : null;
	}

	/**
	 * Makes sure the thumb file exists, so that its URL is never a 404 on a
	 * host that serves /media directly. Kirby only queues a thumb until it is
	 * saved, and reading its size (an asset method) saves it too, unguarded.
	 * So the folder is checked first; a failed save is caught. Never throws.
	 */
	private static function store(FileVersion $version): bool
	{
		if ($version->exists()) {
			return true;
		}
		try {
			if (!self::writable(dirname($version->root()))) {
				return false;
			}
			$version->save();
			return $version->exists();
		} catch (\Throwable) {
			return false;
		}
	}

	private static function text(mixed $value): string
	{
		return is_string($value) ? $value : '';
	}

	/**
	 * One thumb per width, or null as soon as one of them cannot be made.
	 *
	 * @param int[] $widths
	 * @param \Closure(int): ?FileVersion $thumb
	 * @return array<int, FileVersion>|null
	 */
	private static function variants(array $widths, \Closure $thumb): array|null
	{
		$versions = [];
		foreach ($widths as $width) {
			$version = $thumb($width);
			if ($version === null) {
				return null;
			}
			$versions[$width] = $version;
		}
		return $versions;
	}

	/**
	 * The folder exists and is writable, or it does not exist yet and the
	 * nearest existing folder above it is writable, so it can be created.
	 * Creates nothing itself.
	 */
	private static function writable(string $dir): bool
	{
		while (!is_dir($dir)) {
			$parent = dirname($dir);
			if ($parent === $dir) {
				return false;
			}
			$dir = $parent;
		}
		return is_writable($dir);
	}
}
