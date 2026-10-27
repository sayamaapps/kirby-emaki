<?php

/**
 * Writes the three JPEGs of the browser fixture into tests/fixture/img/ with GD.
 * They are generated, not committed: global-setup.ts runs this before the browser tests.
 *
 *   php tests/browser/make-fixture-images.php
 */

$dir = dirname(__DIR__) . '/fixture/img';
if (is_dir($dir) === false && mkdir($dir, 0755, true) === false) {
	fwrite(STDERR, "Cannot create $dir\n");
	exit(1);
}

// Sizes match the width and height attributes in tests/fixture/index.html.
$pictures = [
	'innenhof.jpg' => [800, 600],
	'eingang.jpg'  => [600, 900],
	'garten.jpg'   => [800, 800],
];

foreach ($pictures as $name => [$width, $height]) {
	// A sky, a sun and a meadow: fictional pictures for the tests.
	$image = imagecreatetruecolor($width, $height);
	imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 30, 90, 140));
	imagefilledellipse($image, (int)($width * 0.7), (int)($height * 0.4), (int)($width * 0.3), (int)($height * 0.3), imagecolorallocate($image, 240, 200, 60));
	imagefilledrectangle($image, 0, (int)($height * 0.75), $width, $height, imagecolorallocate($image, 60, 140, 70));
	imagejpeg($image, $dir . '/' . $name, 85);
}
