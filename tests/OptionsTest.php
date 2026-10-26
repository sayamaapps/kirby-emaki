<?php

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Emaki\Options;

/**
 * Options are read as sayamaapps.emaki.<key>, with the older spelling emaki.<key>
 * as fallback. The fallback is reported as a deprecation in debug mode.
 */
#[CoversClass(Options::class)]
final class OptionsTest extends PluginTestCase
{
	public function testNewSpellingIsNotReported(): void
	{
		$deprecations = [];
		set_error_handler(
			function (int $level, string $message) use (&$deprecations): bool {
				$deprecations[] = $message;
				return true;
			},
			E_USER_DEPRECATED
		);

		try {
			$kirby = new App(['options' => ['debug' => true, 'sayamaapps.emaki.active' => true]]);
			$this->assertTrue(Options::active($kirby));
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $deprecations);
	}

	public function testNewSpellingSwitchesEmakiOn(): void
	{
		$kirby = new App(['options' => ['sayamaapps.emaki.active' => true]]);

		$this->assertTrue(Options::active($kirby), 'sayamaapps.emaki.active is read');
	}

	public function testNewSpellingWinsWhenBothAreSet(): void
	{
		$kirby = new App(['options' => ['sayamaapps.emaki.active' => false, 'emaki.active' => true]]);

		$this->assertFalse(Options::active($kirby), 'sayamaapps.emaki.active wins');
	}

	public function testNotSetFallsBackToTheDefault(): void
	{
		$kirby = new App(['options' => []]);

		$this->assertSame('dflt', Options::get($kirby, 'missing', 'dflt'), 'unset: the default');
		$this->assertFalse(Options::active($kirby), 'unset: off');
	}

	public function testNullCountsAsNotSet(): void
	{
		$kirby = new App(['options' => ['sayamaapps.emaki.sizes' => null, 'emaki.sizes' => '50vw']]);

		$this->assertSame('50vw', Options::get($kirby, 'sizes', '100vw'), 'null falls through to the older spelling');
	}

	public function testOldSpellingIsReportedInDebugMode(): void
	{
		$deprecations = [];
		set_error_handler(
			function (int $level, string $message) use (&$deprecations): bool {
				$deprecations[] = $message;
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

		$this->assertSame(['Emaki: emaki.active is deprecated, use sayamaapps.emaki.active'], $deprecations);
	}

	public function testOldSpellingStillWorks(): void
	{
		$kirby = new App(['options' => ['emaki.active' => true]]);

		$this->assertTrue(Options::active($kirby), 'emaki.active is read as fallback');
	}
}
