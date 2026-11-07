<?php
/**
 * Plugin Name: ChinaMudLogging Legacy Query Redirects
 * Description: Preserve search, product and campaign context on existing legacy page redirects.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

function cml_legacy_query_target(array $map, string $path, string $query): ?string {
    $destination = $map[$path] ?? null;
    if ($query === '' || !is_string($destination) || $destination === $path) return null;
    // Only existing, same-site plain paths are eligible. Media, sitemap and future
    // mappings that already contain query/fragment semantics keep their own handlers.
    if (!preg_match('~^/(?!/)[^?#\r\n]*$~D', $destination) || preg_match('/[\r\n]/', $query)) return null;
    return home_url($destination) . '?' . $query;
}

function cml_redirect_legacy_query(): void {
    $request = $_SERVER['REQUEST_URI'] ?? '';
    $path = wp_parse_url($request, PHP_URL_PATH);
    $query = wp_parse_url($request, PHP_URL_QUERY);
    $map = get_option('cml_redirects', []);
    if (!is_string($path) || !is_string($query) || !is_array($map)) return;
    $target = cml_legacy_query_target($map, $path, $query);
    if ($target === null) return;
    wp_safe_redirect($target, 301, 'ChinaMudLogging legacy query');
    exit;
}
add_action('template_redirect', 'cml_redirect_legacy_query', 0);
