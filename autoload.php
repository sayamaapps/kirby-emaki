<?php

/**
 * Class map for Kirby's F::loadClasses(). Keys are lowercase class names,
 * values are paths relative to the plugin root. tests/AutoloadTest.php checks the
 * map against src/.
 */

return [
	'sayamaapps\\emaki\\i18n' => 'src/I18n.php',
	'sayamaapps\\emaki\\images' => 'src/Images.php',
	'sayamaapps\\emaki\\options' => 'src/Options.php',
	'sayamaapps\\emaki\\presets' => 'src/Presets.php',
	'sayamaapps\\emaki\\viewer' => 'src/Viewer.php',
];
