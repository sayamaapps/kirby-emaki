<?php

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Emaki\Options;

/**
 * Options are read as sayamaapps.emaki.<key>, falling back to the 1.x name
 * emaki.<key>. The new name wins when both are set. Reading the 1.x name
 * reports nothing, in debug mode too.
 */
#[CoversClass(Options::class)]
final class OptionsPrefixTest extends PluginTestCase
{
	public function testLegacyWrapperReadsTheNewSpelling(): void
	{
		require_once dirname(__DIR__) . '/legacy.php';

		$kirby = new App(['options' => ['sayamaapps.emaki.active' => true]]);
		$kirby->plugins();

		$this->assertTrue(emaki_option('active'), 'emaki_option() reads sayamaapps.emaki.active');
	}

	public function testNewSpellingFalseWinsOverOldTrue(): void
	{
		$kirby = new App(['options' => ['sayamaapps.emaki.active' => false, 'emaki.active' => true]]);

		$this->assertFalse(Options::active($kirby), 'a set new value counts, even when false');
	}

	public function testNewSpellingIsRead(): void
	{
		$kirby = new App(['options' => ['sayamaapps.emaki.active' => true]]);

		$this->assertTrue(Options::active($kirby), 'sayamaapps.emaki.active switches Emaki on');
	}

	public function testNewSpellingWinsWhenBothAreSet(): void
	{
		$kirby = new App(['options' => ['sayamaapps.emaki.sizes' => '50vw', 'emaki.sizes' => '80vw']]);

		$this->assertSame('50vw', Options::get($kirby, 'sizes', '100vw'), 'the new spelling wins');
	}

	public function testNotSetGivesTheDefault(): void
	{
		$kirby = new App(['options' => []]);

		$this->assertSame('dflt', Options::get($kirby, 'missing', 'dflt'), 'neither spelling set: the default');
		$this->assertFalse(Options::active($kirby), 'neither spelling set: off');
	}

	public function testNullInNewSpellingFallsBackToOldSpelling(): void
	{
		$kirby = new App(['options' => ['sayamaapps.emaki.sizes' => null, 'emaki.sizes' => '80vw']]);

		$this->assertSame('80vw', Options::get($kirby, 'sizes', '100vw'), 'null is not set, the 1.x value applies');
	}

	public function testOldSpellingIsReadWithoutDeprecation(): void
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
			$kirby = new App(['options' => ['debug' => true, 'emaki.active' => true, 'emaki.sizes' => '80vw']]);
			$this->assertTrue(Options::active($kirby), 'emaki.active is still read');
			$this->assertSame('80vw', Options::get($kirby, 'sizes', '100vw'), 'emaki.sizes is still read');
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $reported, 'the 1.x spelling reports no runtime deprecation');
	}
}
