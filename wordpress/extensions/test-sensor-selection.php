<?php
/** Isolated WordPress integration test. Never run the fixture seeding on production. */
if (!defined('CML_SENSOR_ISOLATED_TEST') || !CML_SENSOR_ISOLATED_TEST) exit;
require_once '/wordpress/wp-load.php';
require_once '/cml/extensions/cml-sensor-selection.php';
$passed = 0;
$assert = function ($ok, $label) use (&$passed) { if (!$ok) throw new RuntimeException($label); $passed++; };
$contact = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Contact', 'post_name' => 'contact-us']);
$catalogue = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Products', 'post_name' => 'product']);
update_option('cml_pages', ['en' => ['contact' => $contact, 'products' => $catalogue]]);
foreach (cml_sensor_records() as $slug => $record) {
    $id = wp_insert_post(['post_type' => 'cml_product', 'post_status' => 'publish', 'post_title' => $record[2], 'post_name' => $slug, 'post_content' => '<p>SOURCE-SENTINEL-UNCHANGED</p>']);
    $GLOBALS['wp_query'] = new WP_Query(['post_type' => 'cml_product', 'p' => $id]); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query']; $GLOBALS['post'] = get_post($id); setup_postdata($GLOBALS['post']);
    $c = cml_sensor_context();
    $assert(($c['slug'] ?? '') === $slug, 'Exact product scope ' . $slug);
    $assert(str_contains(apply_filters('wpseo_title', 'old'), $record[1]), 'Product metadata ' . $slug);
    $hero = apply_filters('render_block_cml/product', '<h1>ORIGINAL-H1</h1>');
    $assert(substr_count($hero, '<h1>') === 1 && str_contains($hero, 'cml-sensor-heading'), 'Preserve one product H1 ' . $slug);
    $html = apply_filters('render_block_core/post-content', '<p>SOURCE-SENTINEL-UNCHANGED</p>');
    $assert(str_contains($html, '<p>SOURCE-SENTINEL-UNCHANGED</p>'), 'Source preserved ' . $slug);
    $assert(str_contains($html, 'cml-sensor-documents') && str_contains($html, 'not a set of available downloads'), 'Document status is explicit ' . $slug);
    $assert(!preg_match('/href="[^"]*\.pdf(?:\?|"|$)/i', $html), 'No invented PDF ' . $slug);
    $assert(!str_contains($html, '<form') && !str_contains($html, '<script'), 'No extra sending or analytics ' . $slug);
    $assert(!str_contains($html, '5~30VDC') && !str_contains($html, '24VDC'), 'No source parameter transplant ' . $slug);
    $assert(str_contains($html, 'product=' . rawurlencode($record[2])), 'Correct RFQ context ' . $slug);
    if ($record[0] === 'flow') $assert(str_contains($html, 'absolute') && str_contains($html, 'mud flow meter'), 'Flow query intent and measurement boundary');
}
foreach (['unrelated-sensor', 'mud-temperature-sensor-ar'] as $slug) {
    $id = wp_insert_post(['post_type' => 'cml_product', 'post_status' => 'publish', 'post_title' => 'Unrelated', 'post_name' => $slug]);
    $GLOBALS['wp_query'] = new WP_Query(['post_type' => 'cml_product', 'p' => $id]);
    $assert(cml_sensor_context() === [], 'Unrelated/localized record unchanged');
    $assert(apply_filters('render_block_core/post-content', 'untouched') === 'untouched', 'Non-target body unchanged');
}
$report = ['isolatedWordPress' => true, 'assertions' => $passed, 'products' => 6, 'passed' => true];
file_put_contents('/cml-test-results/sensor-integration.json', wp_json_encode($report, JSON_PRETTY_PRINT));
echo wp_json_encode($report);
