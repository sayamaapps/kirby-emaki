<?php

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Emaki\Images;
use Sayamaapps\Emaki\Options;
use Sayamaapps\Emaki\Viewer;

/**
 * The 1.x names still work: the old class names are aliases of the new classes,
 * and the old functions delegate to them and report a deprecation in debug mode.
 */
#[CoversNothing]
final class LegacyTest extends TestCase
{
	public function testOldClassNamesAreAliasesOfTheNewClasses(): void
	{
		require_once dirname(__DIR__) . '/legacy.php';

		$this->assertSame(Images::class, (new ReflectionClass('Emaki\\Images'))->getName());
		$this->assertSame(Options::class, (new ReflectionClass('Emaki\\Options'))->getName());
		$this->assertTrue(class_exists('Emaki\\I18n'), 'Emaki\\I18n is an alias');
		$this->assertTrue(class_exists('Emaki\\Presets'), 'Emaki\\Presets is an alias');
	}

	public function testOldOptionFunctionReadsTheNewOptionAndIsDeprecated(): void
	{
		require_once dirname(__DIR__) . '/legacy.php';

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
			$kirby->plugins();
			$this->assertTrue(emaki_option('active'), 'emaki_option() reads the new option');
		} finally {
			restore_error_handler();
		}

		$this->assertSame(
			['emaki_option() is deprecated, use Sayamaapps\\Emaki\\Options::get()'],
			$deprecations
		);
	}

	public function testOldTagsFunctionDelegatesToTheViewer(): void
	{
		require_once dirname(__DIR__) . '/legacy.php';

		// Emaki is off: both print nothing. The old function reads the current app, which is this one.
		$kirby = new App(['options' => ['sayamaapps.emaki.active' => false]]);
		$this->assertSame(Viewer::tags($kirby), emaki_tags(), 'emaki_tags() and Viewer::tags() agree while Emaki is off');
	}
}
