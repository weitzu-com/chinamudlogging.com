<?php
/**
 * Plugin Name: ChinaMudLogging Catalogue Pagination SEO
 * Description: Keep independent catalogue pages canonical and filtered results out of the index.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

function cml_pagination_base(): string {
    if (is_admin() || is_404() || is_preview()) return '';
    if (is_tax('cml_family')) {
        $url = get_term_link(get_queried_object());
        return is_wp_error($url) ? '' : $url;
    }
    if (is_page()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post && $post->post_status === 'publish' && has_block('cml/catalogue', $post)) return get_permalink($post);
    }
    return '';
}

function cml_pagination_filtered(): bool {
    if (cml_pagination_base() === '') return false;
    foreach (['q', 'family'] as $key) {
        if (isset($_GET[$key]) && (!is_string($_GET[$key]) || trim(wp_unslash($_GET[$key])) !== '')) return true;
    }
    return false;
}

function cml_pagination_number(): int {
    $value = $_GET['catalog_page'] ?? '1';
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,5}$/D', $value)) return 1;
    return (int) $value;
}

function cml_pagination_canonical($canonical) {
    $base = cml_pagination_base();
    $number = cml_pagination_number();
    if ($base === '' || $number < 2 || cml_pagination_filtered()) return $canonical;
    // Derive host/path from WordPress, never from request headers or arbitrary parameters.
    return add_query_arg('catalog_page', $number, $base);
}
add_filter('wpseo_canonical', 'cml_pagination_canonical', 40);
add_filter('wpseo_opengraph_url', 'cml_pagination_canonical', 40);
add_filter('get_canonical_url', 'cml_pagination_canonical', 40);

add_filter('wpseo_schema_webpage', function (array $data): array {
    $canonical = cml_pagination_canonical('');
    if ($canonical === '') return $data;
    $old = $data['@id'] ?? '';
    $fragment = is_string($old) && str_contains($old, '#') ? '#' . explode('#', $old, 2)[1] : '';
    $data['@id'] = $canonical . $fragment;
    $data['url'] = $canonical;
    foreach ($data['potentialAction'] ?? [] as $key => $action) {
        if (($action['@type'] ?? '') === 'ReadAction' && isset($action['target'])) $data['potentialAction'][$key]['target'] = [$canonical];
    }
    return $data;
}, 40);

add_filter('pll_rel_hreflang_attributes', function (array $links): array {
    // Page 2 is not an equivalent of another language's unpaginated ten-product page.
    if (cml_pagination_base() !== '' && (cml_pagination_number() > 1 || cml_pagination_filtered())) return [];
    return $links;
}, 40);

add_filter('wpseo_robots', function ($robots) {
    if (!cml_pagination_filtered() || !is_string($robots)) return $robots;
    $directives = array_filter(array_map('trim', explode(',', $robots)), fn($value) => strtolower($value) !== 'index');
    if (!in_array('noindex', array_map('strtolower', $directives), true)) array_unshift($directives, 'noindex');
    return implode(', ', $directives);
}, 40);
add_filter('wp_robots', function (array $robots): array {
    if (cml_pagination_filtered()) {
        unset($robots['index']);
        $robots['noindex'] = true;
    }
    return $robots;
}, 40);
