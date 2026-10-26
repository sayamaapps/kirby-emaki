<?php

// Options Emaki reads, relative to the plugin: `sayamaapps.emaki.<key>`.
// Each value is null on purpose. Options::get() gives the default at every read,
// and a default here would hide the older spelling `emaki.<key>` (fallback).
return [
	'active'    => null,
	'language'  => null,
	'texts'     => null,
	'script'    => null,
	'style'     => null,
	'srcset'    => null,
	'thumbnail' => null,
	'sizes'     => null,
	'format'    => null,
	'quality'   => null,
];
