<?php
defined('ABSPATH') || exit;
add_action('after_setup_theme', function () { add_theme_support('post-thumbnails'); add_theme_support('editor-styles'); add_editor_style('assets/site.css'); });
add_action('wp_enqueue_scripts', function () { wp_enqueue_style('cml-site', get_theme_file_uri('assets/site.css'), [], filemtime(get_theme_file_path('assets/site.css'))); });

require_once __DIR__ . '/inc/field-home.php';
