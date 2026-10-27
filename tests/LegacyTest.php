<?php

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Emaki\I18n;
use Sayamaapps\Emaki\Images;
use Sayamaapps\Emaki\Options;
use Sayamaapps\Emaki\Viewer;

/**
 * The 1.x names still work: the old class names are aliases of the new classes,
 * and the old functions delegate to them. Nothing reports a deprecation at runtime.
 */
#[CoversClass(I18n::class)]
#[CoversClass(Images::class)]
#[CoversClass(Options::class)]
#[CoversClass(Viewer::class)]
final class LegacyTest extends PluginTestCase
{
	public function testOldClassNamesAreAliasesOfTheNewClasses(): void
	{
		require_once dirname(__DIR__) . '/legacy.php';

		$this->assertSame(Images::class, (new ReflectionClass('Emaki\\Images'))->getName());
		$this->assertSame(Options::class, (new ReflectionClass('Emaki\\Options'))->getName());
		$this->assertTrue(class_exists('Emaki\\I18n'), 'Emaki\\I18n is an alias');
		$this->assertTrue(class_exists('Emaki\\Presets'), 'Emaki\\Presets is an alias');
	}

	public function testOldOptionFunctionReadsTheOptionWithoutDeprecation(): void
	{
		require_once dirname(__DIR__) . '/legacy.php';

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
			$kirby->plugins();
			$this->assertTrue(emaki_option('active'), 'emaki_option() reads emaki.active');
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $reported, 'the wrapper reports nothing at runtime');
	}

	public function testOldTagsFunctionDelegatesToTheViewer(): void
	{
		require_once dirname(__DIR__) . '/legacy.php';

		// Emaki is off: both print nothing. The old function reads the current app, which is this one.
		$kirby = new App(['options' => ['emaki.active' => false]]);
		$this->assertSame(Viewer::tags($kirby), emaki_tags(), 'emaki_tags() and Viewer::tags() agree while Emaki is off');
	}
}
