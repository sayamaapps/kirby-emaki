<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// The plugin's classes, until they are loaded through autoload.php.
foreach (['I18n', 'Options', 'Presets', 'Images'] as $class) {
	require_once dirname(__DIR__) . '/src/' . $class . '.php';
}

// Kirby's Whoops error handler stays registered after every App is created, so
// the risky-test check would flag each Kirby test. PHPUnit reports errors itself.
Kirby\Cms\App::$enableWhoops = false;

require_once __DIR__ . '/helpers.php';
