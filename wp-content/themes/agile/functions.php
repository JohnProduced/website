<?php

/**
 * The functions file to initialize everything - add features, extensions, override hooks and filters.
 *
 *
 * @package Agile
 * @subpackage Functions
 * @version 1.0
 * @author LiveMesh
 * @copyright Copyright (c) 2012, LiveMesh
 * @link https://www.livemeshthemes.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */


global $theme_cache, $options_cache, $mo_theme;

$theme_cache = array();
$options_cache = array();

/* Load the LiveMesh theme framework. */
require_once( get_template_directory() . '/framework/framework.php' );
if (!isset($mo_theme)) {
$mo_theme = new MO_Framework();
}








?>