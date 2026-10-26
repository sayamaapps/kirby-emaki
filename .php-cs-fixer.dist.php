<?php

return Kirby\PhpCs\Config::create()->setFinder(
	PhpCsFixer\Finder::create()
		->exclude('vendor')
		->exclude('node_modules')
		->exclude('tests/browser/node_modules')
		->in(__DIR__)
);
