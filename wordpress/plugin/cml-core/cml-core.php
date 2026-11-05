<?php
/**
 * Plugin Name: ChinaMudLogging Core
 * Description: Product catalogue, multilingual rendering, migration and private inquiry receipt.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Text Domain: cml-core
 */
defined('ABSPATH') || exit;
define('CML_CORE_DIR', __DIR__);
require_once __DIR__ . '/includes/catalogue.php';
require_once __DIR__ . '/includes/inquiries.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/import.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/funnel.php';

register_activation_hook(__FILE__, function () {
    cml_register_catalogue();
    cml_install_inquiries();
    flush_rewrite_rules();
});
add_action('init', 'cml_register_catalogue');
add_action('init', function () {
    wp_register_script('cml-editor', plugins_url('editor.js', __FILE__), ['wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components'], filemtime(CML_CORE_DIR . '/editor.js'), true);
    foreach (['header', 'footer', 'home', 'catalogue', 'product', 'inquiry', 'applications', 'measurement'] as $view) {
        register_block_type('cml/' . $view, [
            'api_version' => 3,
            'editor_script' => 'cml-editor',
            'render_callback' => fn($attributes) => cml_render($view, $attributes),
            'attributes' => ['limit' => ['type' => 'integer', 'default' => 12], 'hero' => ['type' => 'string'], 'intro' => ['type' => 'string']],
        ]);
    }
});
add_action('wp_enqueue_scripts', function () {
    if (is_page() && has_block('cml/inquiry', get_post())) {
        wp_enqueue_script('cml-inquiry', plugins_url('inquiry.js', __FILE__), ['cml-funnel'], filemtime(CML_CORE_DIR . '/inquiry.js'), true);
    }
});
add_action('template_redirect', function () {
    $map = get_option('cml_redirects', []);
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($path === '/sitemap.xml') {
        wp_safe_redirect(home_url(defined('WPSEO_VERSION') ? '/sitemap_index.xml' : '/wp-sitemap.xml'), 301, 'ChinaMudLogging sitemap');
        exit;
    }
    $assets = get_option('cml_asset_redirects', []);
    if (isset($assets[$path])) {
        wp_safe_redirect($assets[$path], 301, 'ChinaMudLogging media');
        exit;
    }
    if (isset($map[$path]) && $map[$path] !== $path) {
        wp_safe_redirect(home_url($map[$path]), 301, 'ChinaMudLogging');
        exit;
    }
});
// Yoast owns metadata when installed; the fallback covers a fresh staging install.
add_action('wp_head', function () {
    if (defined('WPSEO_VERSION') || !is_singular()) return;
    $description = get_post_meta(get_queried_object_id(), '_cml_description', true);
    if ($description) echo '<meta name="description" content="' . esc_attr($description) . '">';
});
function cml_front_title(string $title): string {
    return is_front_page() ? get_the_title(get_queried_object_id()) : $title;
}
add_filter('pre_get_document_title', 'cml_front_title', 20);
add_filter('wpseo_title', 'cml_front_title', 20);
add_action('wp_head', function () {
    if (!is_singular('cml_product')) return;
    $id = get_queried_object_id();
    $schema = ['@context' => 'https://schema.org', '@type' => 'Product',
        'name' => get_the_title($id), 'description' => get_the_excerpt($id),
        'url' => get_permalink($id), 'inLanguage' => get_bloginfo('language')];
    if (has_post_thumbnail($id)) $schema['image'] = get_the_post_thumbnail_url($id, 'full');
    if ($sku = get_post_meta($id, '_cml_sku', true)) $schema['sku'] = $sku;
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
});
