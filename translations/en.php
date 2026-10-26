<?php

/**
 * English strings — the default and the fallback for missing keys.
 * `{name}` placeholders are filled in by the script. tests/I18nTest.php checks that
 * every language file has the same keys and placeholders as this one.
 * Keys are public; never rename one without a CHANGELOG entry.
 */
return [
	// Accessible name of the dialog while it is open; {n} and {count} are numbers.
	'sayamaapps.emaki.dialog'       => 'Image {n} of {count}',
	// Visible counter under the image (aria-live).
	'sayamaapps.emaki.counter'      => '{n} / {count}',
	// Button names (aria-label; the buttons show symbols).
	'sayamaapps.emaki.button.close' => 'Close',
	'sayamaapps.emaki.button.prev'  => 'Previous image',
	'sayamaapps.emaki.button.next'  => 'Next image',
];
