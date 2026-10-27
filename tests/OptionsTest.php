<?php

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Emaki\Options;

/**
 * Options are read as emaki.<key>, flat or nested. `null` counts as not set.
 * Reading them reports nothing, in debug mode too.
 */
#[CoversClass(Options::class)]
final class OptionsTest extends PluginTestCase
{
	public function testFlatSpellingSwitchesEmakiOn(): void
	{
		$kirby = new App(['options' => ['emaki.active' => true]]);

		$this->assertTrue(Options::active($kirby), 'emaki.active is read');
	}

	public function testLanguageOptionWins(): void
	{
		$kirby = new App(['options' => ['emaki.language' => 'de']]);

		$this->assertSame('de', Options::language($kirby), 'emaki.language is the first choice');
	}

	public function testNestedSpellingReadsOtherKeys(): void
	{
		$kirby = new App(['options' => ['emaki' => ['sizes' => '50vw']]]);

		$this->assertSame('50vw', Options::get($kirby, 'sizes', '100vw'), 'nested value beats the default');
		$this->assertSame('100vw', Options::get($kirby, 'format', '100vw'), 'unset nested key: the default');
	}

	public function testNestedSpellingSwitchesEmakiOn(): void
	{
		$kirby = new App(['options' => ['emaki' => ['active' => true]]]);

		$this->assertTrue(Options::active($kirby), 'nested emaki.active is read');
	}

	public function testNotSetFallsBackToTheDefault(): void
	{
		$kirby = new App(['options' => []]);

		$this->assertSame('dflt', Options::get($kirby, 'missing', 'dflt'), 'unset: the default');
		$this->assertFalse(Options::active($kirby), 'unset: off');
	}

	public function testNullCountsAsNotSet(): void
	{
		$kirby = new App(['options' => ['emaki.sizes' => null]]);

		$this->assertSame('100vw', Options::get($kirby, 'sizes', '100vw'), 'null gives the default');
	}

	public function testOnlyTrueSwitchesEmakiOn(): void
	{
		$kirby = new App(['options' => ['emaki.active' => 'yes']]);

		$this->assertFalse(Options::active($kirby), 'a value other than true does not switch on');
	}

	public function testReadingReportsNoDeprecation(): void
	{
		$reported = [];
		set_error_handler(
			function (int $level, string $message) use (&$reported): bool {
				$reported[] = $message;
				return true;
			},
			E_USER_DEPRECATED
		);

		try {
			$kirby = new App(['options' => ['debug' => true, 'emaki.active' => true]]);
			$this->assertTrue(Options::active($kirby));
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $reported, 'no runtime deprecation in debug mode');
	}
}
