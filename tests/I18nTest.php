<?php

/**
 * The labels: every language file has the keys and placeholders of en.php; the
 * 1.x keys carry the same texts; the labels come from Kirby's translations in the
 * language of the request, with the `texts` overrides in front.
 */

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Emaki\I18n;

#[CoversClass(I18n::class)]
final class I18nTest extends PluginTestCase
{
	public function testEveryLanguageHasTheKeysAndPlaceholdersOfEnglish(): void
	{
		$en = require dirname(__DIR__) . '/translations/en.php';

		foreach (I18n::LANGUAGES as $language) {
			$table = require dirname(__DIR__) . '/translations/' . $language . '.php';

			$this->assertSame(array_keys($en), array_keys($table), "$language has the keys of en, in order");
			foreach ($en as $key => $text) {
				preg_match_all('/\{[a-z]+\}/', $text, $expected);
				preg_match_all('/\{[a-z]+\}/', $table[$key], $actual);
				sort($expected[0]);
				sort($actual[0]);
				$this->assertSame($expected[0], $actual[0], "$language.$key has the placeholders of en");
				$this->assertNotSame('', trim($table[$key]), "$language.$key is not empty");
			}
		}
	}

	public function testInvalidOverridesAreIgnored(): void
	{
		new App(['options' => []]);

		I18n::setLanguage('de');
		I18n::setOverrides('not an array');

		$this->assertSame('Schließen', I18n::labels()['close'], 'invalid overrides are ignored');
	}

	public function testLabelsAreReadFromKirbyTranslations(): void
	{
		new App(['options' => []]);

		I18n::setLanguage('de');
		I18n::setOverrides(null);

		$this->assertSame('de', I18n::language(), 'language set');
		$this->assertSame('Schließen', I18n::labels()['close'], 'German label from the Kirby translations');
		$this->assertSame('Bild {n} von {count}', I18n::labels()['dialog'], 'the placeholders stay for the script');
	}

	public function testOldKeysCarryTheSameTexts(): void
	{
		$translations = require dirname(__DIR__) . '/config/translations.php';

		foreach (I18n::LANGUAGES as $language) {
			foreach ($translations[$language] as $key => $text) {
				if (str_starts_with($key, 'sayamaapps.emaki.')) {
					$old = str_replace('sayamaapps.emaki.', 'emaki.', $key);
					$this->assertSame($text, $translations[$language][$old] ?? null, "$language: the 1.x key of $key");
				}
			}
		}
	}

	public function testOverridesApplyFlatAndPerLanguage(): void
	{
		new App(['options' => []]);

		I18n::setLanguage('de');
		I18n::setOverrides(['button.close' => 'Zu', 'de' => ['button.next' => 'Weiter'], 'en' => ['button.prev' => 'Back']]);

		$labels = I18n::labels();
		$this->assertSame('Zu', $labels['close'], 'flat override applies');
		$this->assertSame('Weiter', $labels['next'], 'language override applies');
		$this->assertSame('Vorheriges Bild', $labels['prev'], 'other language override is ignored');
	}

	public function testUnknownLanguageFallsBackToEnglish(): void
	{
		new App(['options' => []]);

		I18n::setLanguage('xx');
		I18n::setOverrides(null);

		$this->assertSame('en', I18n::language(), 'unknown language falls back to English');
		$this->assertSame(['dialog', 'close', 'prev', 'next', 'counter'], array_keys(I18n::labels()), 'labels for the script tag');
		$this->assertSame('Image {n} of {count}', I18n::labels()['dialog'], 'English label');
	}
}
