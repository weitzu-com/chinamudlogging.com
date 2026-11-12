<?php
/** WP-CLI eval-file: only SELECT/read APIs; stdout contains private audit data. */
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('CLI only');
$p = get_post(600);
if (!$p || $p->post_type !== 'cml_product' || $p->post_name !== 'ultrasonic-sensor-bracket-mud-logging') {
    throw new RuntimeException('Product identity mismatch');
}
if (!function_exists('pll_get_post_language') || pll_get_post_language(600, 'slug') !== 'en') {
    throw new RuntimeException('English language identity unverified');
}
$terms = wp_get_object_terms(600, 'cml_family', ['fields' => 'all']);
if (is_wp_error($terms)) throw new RuntimeException($terms->get_error_message());
$data = [
    'exportedAt' => gmdate('c'), 'site' => home_url('/'),
    'post' => $p->to_array(), 'meta' => get_post_meta(600),
    'language' => 'en', 'families' => $terms,
    'revisions' => array_map(fn($r) => $r->to_array(), array_values(wp_get_post_revisions(600, ['posts_per_page' => -1]))),
];
echo wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
