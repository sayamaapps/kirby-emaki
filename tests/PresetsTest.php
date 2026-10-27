<?php

/**
 * Sayamaapps\Emaki\Presets::widths() without Kirby: both spellings of `thumbs.srcsets`
 * (a plain list and `width => 'condition'`, plus the `descriptor => [options]`
 * form), the cap at the file's width, the fallbacks and the invalid entries.
 * Kirby's App is replaced by a stand-in that answers `option()` with dotted
 * keys, the one method Emaki reads.
 */

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Emaki\Options;
use Sayamaapps\Emaki\Presets;

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

	/** A site with these thumbs.srcsets presets. */
	private function site(array $srcsets): App
	{
		return $this->appWith(['thumbs' => ['srcsets' => $srcsets]]);
	}

	public function testPlainListIsCappedAtTheFileWidth(): void
	{
		$kirby = $this->site(['emaki' => [400, 800, 1600]]);
		$large = Options::LARGE_WIDTHS;

		$this->assertSame([400, 800, 1600], Presets::widths($kirby, 'emaki', $large, 2000), 'file wider than the list: the list as it is');
		$this->assertSame([400, 800, 1600], Presets::widths($kirby, 'emaki', $large, 1600), 'file as wide as the largest');
		$this->assertSame([400, 800, 1000], Presets::widths($kirby, 'emaki', $large, 1000), 'widths above the file are cut, the file width is added');
		$this->assertSame([400, 800], Presets::widths($kirby, 'emaki', $large, 800), 'a width equal to the file is not doubled');
		$this->assertSame([400], Presets::widths($kirby, 'emaki', $large, 400), 'file as wide as the smallest');
	}

	public function testFileNarrowerThanEveryWidthGetsOneVariant(): void
	{
		$kirby = $this->site(['emaki' => [400, 800, 1600]]);

		$this->assertSame([300], Presets::widths($kirby, 'emaki', Options::LARGE_WIDTHS, 300), 'one variant at its own width');
	}

	public function testWidthKeysWithConditionsAreWidths(): void
	{
		$kirby = $this->site(['emaki' => [400 => 'landscape', 800 => '(min-width: 40rem)']]);
		$large = Options::LARGE_WIDTHS;

		$this->assertSame([400, 800], Presets::widths($kirby, 'emaki', $large, 2000), 'keys are the widths');
		$this->assertSame([400, 600], Presets::widths($kirby, 'emaki', $large, 600), 'capped at the file width');
	}

	public function testDescriptorFormUsesItsWidth(): void
	{
		$kirby = $this->site(['emaki' => ['400w' => ['width' => 400, 'quality' => 70], '800w' => ['width' => 800]]]);
		$large = Options::LARGE_WIDTHS;

		$this->assertSame([400, 800], Presets::widths($kirby, 'emaki', $large, 2000), 'width option is used');
		$this->assertSame([400, 500], Presets::widths($kirby, 'emaki', $large, 500), 'capped at the file width');
	}

	public function testIntegerKeyWithOptionsIsTheWidth(): void
	{
		$kirby = $this->site(['emaki' => [400 => ['quality' => 70], 900 => ['format' => 'webp']]]);

		$this->assertSame([400, 900], Presets::widths($kirby, 'emaki', Options::LARGE_WIDTHS, 2000));
	}

	public function testUnsortedListWithDuplicatesIsAscendingAndUnique(): void
	{
		$kirby = $this->site(['emaki' => [1600, 400, 800, 400]]);

		$this->assertSame([400, 800, 1600], Presets::widths($kirby, 'emaki', Options::LARGE_WIDTHS, 2000));
	}

	public function testInvalidEntriesAreIgnoredAndNumericStringsCount(): void
	{
		$kirby = $this->site(['emaki' => [0 => 'abc', 1 => 0, 2 => -5, 3 => null, 4 => true, 5 => 800, 6 => '600', 'x' => ['width' => 'nope']]]);

		$this->assertSame([600, 800], Presets::widths($kirby, 'emaki', Options::LARGE_WIDTHS, 2000));
	}

	public function testKeysWithConditionsStayWidths(): void
	{
		$kirby = $this->site(['emaki' => [800 => 'x', 1200 => 'y']]);

		$this->assertSame([800, 1200], Presets::widths($kirby, 'emaki', Options::LARGE_WIDTHS, 2000));
	}

	public function testPassedListIsUsedAndSorted(): void
	{
		$large = Options::LARGE_WIDTHS;

		$this->assertSame([300, 900], Presets::widths($this->site([]), [300, 900], $large, 2000), 'a plain list passed as the preset');
		$this->assertSame([300, 900], Presets::widths($this->site([]), [900, 300], $large, 2000), 'a passed list is sorted too');
	}

	public function testMissingPresetUsesTheFallbackList(): void
	{
		$large = Options::LARGE_WIDTHS;

		$this->assertSame($large, Presets::widths($this->site([]), null, $large, 3000), 'null: the fallback list');
		$this->assertSame($large, Presets::widths($this->site([]), 'missing', $large, 3000), 'missing preset name: the fallback list');
	}

	public function testFallbackListIsCappedAtTheFileWidth(): void
	{
		$large = Options::LARGE_WIDTHS;

		$this->assertSame([800, 1000], Presets::widths($this->site([]), null, $large, 1000), '800 plus the file width');
		$this->assertSame([800, 1200, 1800, 2000], Presets::widths($this->site([]), null, $large, 2000), 'capped at 2000 px');
	}

	public function testEmptyPresetUsesTheFallbackWidth(): void
	{
		$tile = Options::TILE_WIDTHS;

		$this->assertSame([960], Presets::widths($this->site(['emaki' => []]), 'emaki', $tile, 2000), 'largest fallback width');
		$this->assertSame([500], Presets::widths($this->site(['emaki' => []]), 'emaki', $tile, 500), 'narrow file: its own width');
	}

	public function testPresetWithOnlyInvalidEntriesUsesTheFallbackWidth(): void
	{
		$kirby = $this->site(['emaki' => ['abc', 0, -1]]);

		$this->assertSame([960], Presets::widths($kirby, 'emaki', Options::TILE_WIDTHS, 2000));
	}

	public function testNoUsableWidthGivesOneVariantAtTheFileWidth(): void
	{
		$this->assertSame([700], Presets::widths($this->site([]), null, [], 700));
	}

	public function testFileWithoutWidthHasNoWidths(): void
	{
		$large = Options::LARGE_WIDTHS;

		$this->assertSame([], Presets::widths($this->site([]), null, $large, 0), 'width 0');
		$this->assertSame([], Presets::widths($this->site([]), null, $large, -4), 'negative width');
	}

	public function testNamedPresetsAreSeparate(): void
	{
		$kirby = $this->site(['emaki' => [400], 'other' => [700]]);

		$this->assertSame([400], Presets::widths($kirby, 'emaki', Options::LARGE_WIDTHS, 2000));
		$this->assertSame([700], Presets::widths($kirby, 'other', Options::LARGE_WIDTHS, 2000));
	}

	/** @return iterable<string, array{int}> */
	public static function fileWidths(): iterable
	{
		foreach ([1, 2, 319, 320, 721, 960, 961, 2400, 9000] as $width) {
			yield "$width px" => [$width];
		}
	}

	#[DataProvider('fileWidths')]
	public function testWidthsAreAscendingUniqueAndNotAboveTheFile(int $fileWidth): void
	{
		$widths = Presets::widths($this->site([]), null, Options::TILE_WIDTHS, $fileWidth);

		$sorted = $widths;
		sort($sorted);
		$this->assertSame($sorted, $widths, 'ascending');
		$this->assertSame(array_values(array_unique($widths)), $widths, 'unique');
		$this->assertLessThanOrEqual($fileWidth, max($widths), 'no width above the file');
	}
}
