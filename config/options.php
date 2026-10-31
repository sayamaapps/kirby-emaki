<?php

// Options Emaki reads, relative to the plugin: `sayamaapps.emaki.<key>`.
// Each value is null on purpose. Options::get() gives the default at every read,
// and a default here would hide the 1.x spelling `emaki.<key>` (fallback).
return [
	'active'    => null,
	'format'    => null,
	'language'  => null,
	'quality'   => null,
	'script'    => null,
	'sizes'     => null,
	'srcset'    => null,
	'style'     => null,
	'texts'     => null,
	'thumbnail' => null,
];
