<?php
/**
 * Plugin Name: ChinaMudLogging HubSpot Inquiry
 * Description: Loads the published English HubSpot inquiry form on request, retaining the local form as a fallback.
 * Version: 1.0.2
 * Requires PHP: 8.2
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/hubspot.php';

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('cml_language') || !is_page() || !has_block('cml/inquiry', get_post())) return;
    wp_enqueue_style('cml-hubspot', plugins_url('hubspot.css', __FILE__), [], filemtime(__DIR__ . '/hubspot.css'));
    wp_enqueue_script('cml-hubspot', plugins_url('hubspot.js', __FILE__), [], filemtime(__DIR__ . '/hubspot.js'), true);
});

add_filter('render_block_cml/inquiry', function (string $content): string {
    if (!function_exists('cml_language') || !str_contains($content, '</aside><form data-cml-inquiry')) return $content;
    $content = str_replace('</aside><form data-cml-inquiry', '</aside><div class="cml-inquiry-options">' . cml_hubspot_form()
        . '<details class="cml-local-inquiry"><summary>' . esc_html(cml_hubspot_copy()['fallback']) . '</summary><form data-cml-inquiry', $content);
    return str_replace('</form></div>', '</form></details></div></div>', $content);
});
