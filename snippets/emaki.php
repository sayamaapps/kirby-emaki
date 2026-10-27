<?php
/**
 * The stylesheet and script tags for the viewer, once per page. Put it where
 * the site prints its other tags (head or end of body); the script is
 * deferred. Prints nothing while `emaki.active` is not true.
 *
 *   <?php snippet('emaki') ?>
 */
?>
<?= \Sayamaapps\Emaki\Viewer::tags(\Kirby\Cms\App::instance()) ?>
