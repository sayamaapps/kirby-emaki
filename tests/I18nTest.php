<?php

/**
 * The language tables: every language has exactly the keys and placeholders
 * of en.php; the fallback chain (override → language → English → key); the
 * labels for the script tag. Kirby-free.
 *   php tests/I18nTest.php
 */

use Emaki\I18n;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;


#[CoversClass(I18n::class)]
final class I18nTest extends TestCase
{
	public function testSuite(): void
	{
		$en = I18n::table('en');

		expect(true, $en !== [], 'English table present');

		foreach (I18n::LANGUAGES as $language) {
		    $table = I18n::table($language);
		    expect(array_keys($en), array_keys($table), "$language has the keys of en, in order");
		    foreach ($en as $key => $text) {
		        preg_match_all('/\{[a-z]+\}/', $text, $a);
		        preg_match_all('/\{[a-z]+\}/', $table[$key], $b);
		        sort($a[0]); sort($b[0]);
		        expect($a[0], $b[0], "$language.$key has the placeholders of en");
		        expect(true, trim($table[$key]) !== '', "$language.$key is not empty");
		    }
		}

		expect([], I18n::table('fr'), 'unknown language: empty table');

		I18n::setLanguage('de');

		I18n::setOverrides(null);

		expect('de', I18n::language(), 'language set');

		expect('Bild 3 von 7', I18n::t('dialog', ['n' => 3, 'count' => 7]), 'placeholders filled');

		expect('Schließen', I18n::t('button.close'), 'German label');

		expect('nope', I18n::t('nope'), 'unknown key falls back to the key');

		I18n::setOverrides(['button.close' => 'Zu', 'de' => ['button.next' => 'Weiter'], 'en' => ['button.prev' => 'Back']]);

		expect('Zu', I18n::t('button.close'), 'flat override applies');

		expect('Weiter', I18n::t('button.next'), 'language override applies');

		expect('Vorheriges Bild', I18n::t('button.prev'), 'other language override is ignored');

		I18n::setOverrides('not an array');

		expect('Schließen', I18n::t('button.close'), 'invalid overrides are ignored');

		I18n::setLanguage('xx');

		expect('en', I18n::language(), 'unknown language falls back to English');

		expect(['dialog', 'close', 'prev', 'next', 'counter'], array_keys(I18n::labels()), 'labels for the script tag');

		expect('Image {n} of {count}', I18n::labels()['dialog'], 'dialog label keeps its placeholders for the script');

	}
}
