<?php

/**
 * English strings — the default and the fallback for missing keys.
 * `{name}` placeholders are filled in by Emaki\I18n::t(). tests/I18nTest.php
 * checks that every language file has the same keys and placeholders as this
 * one. Keys are the contract (SPEC.md §6); never rename one without a
 * CHANGELOG entry.
 */
return [
	// Accessible name of the dialog while it is open; {n} and {count} are numbers.
	'dialog'       => 'Image {n} of {count}',
	// Visible counter under the image (aria-live).
	'counter'      => '{n} / {count}',
	// Button names (aria-label; the buttons show symbols).
	'button.close' => 'Close',
	'button.prev'  => 'Previous image',
	'button.next'  => 'Next image',
];
