<?php
/**
 * Template Name: Home Page
 *
 * A page template for home page layout involving sliders, featured stories and categories.
 * @link https://www.livemeshthemes.com/
 *
 * @package Agile
 * @subpackage Template
 */
get_header(); // displays slider content if so chosen by user 
?>

<?php
/* Let's start the content here for consistency and keep the sliders lonely in header area */
get_template_part("page-content-lite");
?>

<?php get_footer(); ?>



