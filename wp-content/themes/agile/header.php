<?php
/**
 * Header Template
 *
 * This template is loaded for displaying header information for the website. Called from every page of the website.
 *
 * @package Agile
 * @subpackage Template
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>"/>

    <meta http-equiv="X-UA-Compatible" content="IE=Edge;chrome=1">

    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

    <!-- For use in JS files -->
    <script type="text/javascript">
        var template_dir = "<?php echo get_template_directory_uri(); ?>";
    </script>

    <link rel="profile" href="http://gmpg.org/xfn/11"/>

    <link rel="pingback" href="<?php bloginfo('pingback_url'); ?>"/>

    <?php mo_setup_theme_options_for_scripts(); ?>

    <?php wp_head(); // wp_head  ?>

</head>

<body <?php body_class(); ?>>

<?php

$disable_smooth_page_load = mo_get_theme_option('mo_disable_smooth_page_load');

if (empty($disable_smooth_page_load)) {
    echo '<div id="page-loading"></div>';
}

?>

<?php mo_exec_action('start_body'); ?>

<?php echo '<a id="mobile-menu-toggle" href="#"><i class="icon-th-menu"></i>&nbsp;</a>'; ?>

<?php get_template_part('menu', 'mobile'); // Loads the menu-mobile.php template.    ?>

<div id="container">

    <?php mo_exec_action('before_header'); ?>

    <?php
    $header_classes = apply_filters('mo_header_class', array());
    if (!empty($header_classes))
        $header_classes = 'class="' . implode(' ', $header_classes) . '"';
    else
        $header_classes = '';
    ?>

    <header id="header" <?php echo $header_classes; ?>>

        <div class="inner clearfix">

            <div class="wrap">

                <?php mo_exec_action('start_header');

                mo_site_logo();

                mo_site_description();

                mo_display_sidebar('header');

                mo_exec_action('header');

                get_template_part('menu', 'primary'); // Loads the menu-primary.php template.

                mo_exec_action('end_header'); ?>

                <?php if (mo_is_woocommerce_activated()) {
                    mo_display_cart_in_header();
                }?>

            </div>

        </div>

    </header>
    <!-- #header -->

    <?php mo_exec_action('after_header'); ?>

    <?php mo_populate_top_area(); ?>

    <div id="main" class="clearfix">

        <?php mo_exec_action('start_main'); ?>

        <div class="inner clearfix">