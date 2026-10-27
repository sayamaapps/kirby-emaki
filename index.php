<?php

use Kirby\Filesystem\F;

/**
 * Plugin registration for Emaki (絵巻, the picture scroll: one image after the
 * other, unrolled by hand): an image viewer for galleries, served from the
 * site's own server.
 *
 * Kirby loads this file. It registers the class map, the legacy names and the
 * definitions in config/. The logic lives in src/. Off by default: without
 * `'emaki.active' => true` the `emaki` snippet prints nothing, and
 * `emaki/gallery` renders plain links to the large image.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-emaki
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
F::loadClasses(require __DIR__ . '/autoload.php', __DIR__);
require_once __DIR__ . '/legacy.php';

Kirby::plugin(
	name: 'sayamaapps/emaki',
	extends: [
		'translations' => require __DIR__ . '/config/translations.php',
		'snippets'     => require __DIR__ . '/config/snippets.php',
		'fileMethods'  => require __DIR__ . '/config/file-methods.php',
	],
	root: __DIR__
);
