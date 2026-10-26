<?php

/**
 * Emaki\Presets::widths() without Kirby: both spellings of `thumbs.srcsets`
 * (a plain list and `width => 'condition'`, plus the `descriptor => [options]`
 * form), the cap at the file's width, the fallbacks and the invalid entries.
 * Kirby's App is replaced by a stand-in that answers `option()` with dotted
 * keys, the one method Emaki reads.
 *   php tests/PresetsTest.php
 */

use Emaki\Options;
use Emaki\Presets;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Presets::class)]
final class PresetsTest extends TestCase
{
	/** A Kirby App stand-in: option() answers from a plain array with dotted keys, the one method Emaki reads. */
	private function appWith(array $config): App
	{
		$app = $this->createMock(App::class);
		$app->method('option')->willReturnCallback(
			static function (string $key, mixed $default = null) use ($config): mixed {
				$value = $config;
				foreach (explode('.', $key) as $part) {
					if (!is_array($value) || !array_key_exists($part, $value)) {
						return $default;
					}
					$value = $value[$part];
				}
				return $value;
			}
		);
		return $app;
	}

	public function testSuite(): void
	{
		/** A site with these thumbs.srcsets presets. */
		$site = fn (array $srcsets): App => $this->appWith(['thumbs' => ['srcsets' => $srcsets]]);

		$large = Options::LARGE_WIDTHS;

		$tile  = Options::TILE_WIDTHS;

		$kirby = $site(['emaki' => [400, 800, 1600]]);

		expect([400, 800, 1600], Presets::widths($kirby, 'emaki', $large, 2000), 'plain list, file wider than the list: largest is the list\'s own');

		expect([400, 800, 1600], Presets::widths($kirby, 'emaki', $large, 1600), 'plain list, file as wide as its largest');

		expect([400, 800, 1000], Presets::widths($kirby, 'emaki', $large, 1000), 'cap: widths above the file are cut, the file width is added');

		expect([400, 800], Presets::widths($kirby, 'emaki', $large, 800), 'cap: a width equal to the file is not doubled');

		expect([400], Presets::widths($kirby, 'emaki', $large, 400), 'cap: file as wide as the smallest');

		expect([300], Presets::widths($kirby, 'emaki', $large, 300), 'file narrower than every width: one variant at its own width');

		$kirby = $site(['emaki' => [400 => 'landscape', 800 => '(min-width: 40rem)']]);

		expect([400, 800], Presets::widths($kirby, 'emaki', $large, 2000), 'width => condition: keys are the widths');

		expect([400, 600], Presets::widths($kirby, 'emaki', $large, 600), 'width => condition: capped');

		$kirby = $site(['emaki' => ['400w' => ['width' => 400, 'quality' => 70], '800w' => ['width' => 800]]]);

		expect([400, 800], Presets::widths($kirby, 'emaki', $large, 2000), 'descriptor => options: width option is used');

		expect([400, 500], Presets::widths($kirby, 'emaki', $large, 500), 'descriptor => options: capped');

		$kirby = $site(['emaki' => [400 => ['quality' => 70], 900 => ['format' => 'webp']]]);

		expect([400, 900], Presets::widths($kirby, 'emaki', $large, 2000), 'integer key with options: the key is the width');

		$kirby = $site(['emaki' => [1600, 400, 800, 400]]);

		expect([400, 800, 1600], Presets::widths($kirby, 'emaki', $large, 2000), 'unsorted list with a duplicate: ascending, unique');

		$kirby = $site(['emaki' => [0 => 'abc', 1 => 0, 2 => -5, 3 => null, 4 => true, 5 => 800, 6 => '600', 'x' => ['width' => 'nope']]]);

		expect([600, 800], Presets::widths($kirby, 'emaki', $large, 2000), 'non-numeric, zero and negative entries ignored; numeric strings count');

		$kirby = $site(['emaki' => [800 => 'x', 1200 => 'y']]);

		expect([800, 1200], Presets::widths($kirby, 'emaki', $large, 2000), 'keys with conditions stay widths');

		expect([300, 900], Presets::widths($site([]), [300, 900], $large, 2000), 'a plain list passed as the preset');

		expect([300, 900], Presets::widths($site([]), [900, 300], $large, 2000), 'a passed list is sorted too');

		expect($large, Presets::widths($site([]), null, $large, 3000), 'null: the fallback list, no cap needed');

		expect($large, Presets::widths($site([]), 'missing', $large, 3000), 'missing preset name: the fallback list');

		expect([800, 1000], Presets::widths($site([]), null, $large, 1000), 'fallback list is capped at 1000 px: 800 plus the file width');

		expect([800, 1200, 1800, 2000], Presets::widths($site([]), null, $large, 2000), 'fallback list capped at 2000 px');

		expect([960], Presets::widths($site(['emaki' => []]), 'emaki', $tile, 2000), 'empty preset list: one variant at the largest fallback width');

		expect([500], Presets::widths($site(['emaki' => []]), 'emaki', $tile, 500), 'empty preset list, narrow file: one variant at the file width');

		expect([960], Presets::widths($site(['emaki' => ['abc', 0, -1]]), 'emaki', $tile, 2000), 'preset with only invalid entries: the fallback single width');

		expect([700], Presets::widths($site([]), null, [], 700), 'no usable widths anywhere: one variant at the file width');

		expect([], Presets::widths($site([]), null, $large, 0), 'a file without width has no widths');

		expect([], Presets::widths($site([]), null, $large, -4), 'negative width: no widths');

		$kirby = $site(['emaki' => [400], 'other' => [700]]);

		expect([400], Presets::widths($kirby, 'emaki', $large, 2000), 'the named preset');

		expect([700], Presets::widths($kirby, 'other', $large, 2000), 'another preset');

		foreach ([1, 2, 319, 320, 721, 960, 961, 2400, 9000] as $fileWidth) {
			$widths = Presets::widths($site([]), null, $tile, $fileWidth);
			$sorted = $widths;
			sort($sorted);
			expect($sorted, $widths, "ascending for $fileWidth px");
			expect(array_values(array_unique($widths)), $widths, "unique for $fileWidth px");
			expect(true, max($widths) <= $fileWidth, 'no width above ' . $fileWidth . ' px');
		}

	}
}
