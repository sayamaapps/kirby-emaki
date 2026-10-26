<?php

// The labels as Kirby plugin translations, per language. Each text is written once,
// in translations/<language>.php, under `sayamaapps.emaki.<key>`. The same texts are
// also registered under the 1.x key `emaki.<key>`, so `t('emaki.<key>')` keeps working.
$translations = [];
foreach (Sayamaapps\Emaki\I18n::LANGUAGES as $language) {
	$labels = require dirname(__DIR__) . '/translations/' . $language . '.php';
	$translations[$language] = $labels;
	foreach ($labels as $key => $text) {
		$translations[$language][str_replace('sayamaapps.emaki.', 'emaki.', $key)] = $text;
	}
}

return $translations;
