<?php

use Sayamaapps\Emaki\Images;

// `$file->emaki([...])`, see Images::attributes(). Kirby binds $this to the file.
return [
	'emaki' => function (array $options = []): array|null {
		return Images::attributes($this, $options);
	},
];
