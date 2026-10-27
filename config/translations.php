<?php

// The labels as Kirby plugin translations, per language, under the 1.x keys `emaki.<key>`
// (the viewer reads its own tables in translations/<language>.php, see Sayamaapps\Emaki\I18n).
$translations = [];
foreach (Sayamaapps\Emaki\I18n::LANGUAGES as $language) {
	$labels = require dirname(__DIR__) . '/translations/' . $language . '.php';
	foreach ($labels as $key => $text) {
		$translations[$language]['emaki.' . $key] = $text;
	}
}

return $translations;
