<?php
/**
 * A gallery: one container (`data-emaki` = one sequence to leaf through),
 * one tile per file, markup as documented in the README. The site styles the grid
 * itself (classes emaki-gallery, emaki-item); Emaki only provides the stage.
 * An image Kirby cannot make thumbs for is left out and marked with an HTML
 * comment. No container is printed when no tile is left; the comments stay.
 *
 *   <?php snippet('emaki/gallery', ['files' => $page->images()->sortBy('sort')]) ?>
 *   <?php snippet('emaki/gallery', ['files' => $page->gallery()->toFiles(), 'sizes' => '(max-width: 40rem) 50vw, 20rem', 'caption' => 'caption']) ?>
 *
 * @var \Kirby\Cms\Files|iterable $files   the images (non-images are skipped)
 * @var string|null $sizes    `sizes` of the tile <img>; default '(max-width: 40rem) 100vw, 33vw'
 * @var string|null $caption  'alt' (default): the alt text as caption; a field name (e.g. 'caption'): that file field; false: no caption
 * @var string|null $class    extra classes on the container
 * @var string|null $tag      container element, default 'div'
 * @var array|null $attrs    extra container attributes (data-*, aria-*, id only)
 * @var string|null $image    name of a file field whose value becomes data-emaki-image (for counting); default none
 */

$tag     = is_string($tag ?? null) && preg_match('/^[a-z][a-z0-9]*$/', $tag) === 1 ? $tag : 'div';
$class   = is_string($class ?? null) ? trim($class) : '';
$caption ??= 'alt';
$image   = is_string($image ?? null) && $image !== '' ? $image : null;
$options = is_string($sizes ?? null) && $sizes !== '' ? ['sizes' => $sizes] : [];
$active  = \Sayamaapps\Emaki\Options::active(\Kirby\Cms\App::instance());

// Every value goes through esc(); a null value leaves the attribute out.
$attr = static fn (string $name, mixed $value): string => $value === null
	? ''
	: ' ' . $name . '="' . esc((string)$value) . '"';

// Container attributes: data-*, aria-* and id only; data-emaki is Emaki's own and set below.
$extra = '';
foreach (is_array($attrs ?? null) ? $attrs : [] as $name => $value) {
	if (
		is_string($name) &&
		$name !== 'data-emaki' &&
		preg_match('/^(?:(?:data|aria)-[a-z][a-z0-9_-]*|id)\z/', $name) === 1 &&
		(is_string($value) || is_int($value) || is_float($value))
	) {
		$extra .= $attr($name, $value);
	}
}

$fieldText = static function (\Kirby\Cms\File $file, string $name): string {
	$value = $file->content()->get($name)->value();
	return is_string($value) ? $value : '';
};

$items = '';
$tiles = 0;
foreach ($files ?? [] as $file) {
	if (!$file instanceof \Kirby\Cms\File || $file->type() !== 'image') {
		continue;
	}

	$tile = \Sayamaapps\Emaki\Images::attributes($file, $options);
	if ($tile === null) {
		// Kirby cannot make the thumbs (media folder not writable): the image is
		// left out, never linked as the original. The comment keeps the gap visible.
		$items .= '  <!-- emaki: no thumbs for ' . esc($file->filename()) . " -->\n";
		continue;
	}
	$tiles++;
	$imageName = $image === null ? '' : $fieldText($file, $image);

	if ($caption === false) {
		$text = '';
	} elseif (is_string($caption) && $caption !== 'alt') {
		$text = $fieldText($file, $caption);
	} else {
		$text = $tile['alt'];
	}

	$items .= '  <a class="emaki-item"'
		. $attr('href', $tile['href'])
		. $attr('data-srcset', $tile['data-srcset'])
		. $attr('data-sizes', $tile['data-sizes'])
		. $attr('data-emaki-caption', $text)
		. ($imageName !== '' ? $attr('data-emaki-image', $imageName) : '')
		. ">\n"
		. '    <img'
		. $attr('src', $tile['src'])
		. $attr('srcset', $tile['srcset'])
		. $attr('sizes', $tile['sizes'])
		. $attr('width', $tile['width'])
		. $attr('height', $tile['height'])
		. $attr('alt', $tile['alt'])
		. $attr('loading', $tile['loading'])
		. $attr('decoding', $tile['decoding'])
		. ">\n"
		. "  </a>\n";
}

// The container only with at least one tile: a data-emaki container without tiles would be a sequence of nothing.
// Without tiles, only the comments of the images left out are printed.
if ($tiles > 0) {
	echo '<' . $tag . ' class="emaki-gallery' . ($class !== '' ? ' ' . esc($class) : '') . '"'
		. ($active ? ' data-emaki' : '')
		. $extra . ">\n"
		. $items
		. '</' . $tag . ">\n";
} else {
	echo $items;
}
