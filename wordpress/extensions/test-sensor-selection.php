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
    $official = $slug === 'sitrans-probe-lu240-ultrasonic-pit-level-transmitter';
    $assert(str_contains($html, 'cml-sensor-documents') && str_contains($html, $official ? 'data-cml-doc-status="official-family"' : 'not a set of available downloads'), 'Document status is explicit ' . $slug);
    $pdfs = []; preg_match_all('/href="([^"]*\.pdf)"/i', $html, $pdfs);
    $assert($official ? $pdfs[1] === ['https://cache.industry.siemens.com/dl/files/517/109973517/att_1294115/v45/A5E42673511en_LU240_HA_mA_OI_en-US.pdf'] : count($pdfs[1]) === 0, 'Exact official PDF whitelist ' . $slug);
    $assert(!str_contains($html, '<form') && !str_contains($html, '<script'), 'No extra sending or analytics ' . $slug);
    $assert(!str_contains($html, '5~30VDC') && !str_contains($html, '24VDC'), 'No source parameter transplant ' . $slug);
    $assert(str_contains($html, 'product=' . rawurlencode($record[2])), 'Correct RFQ context ' . $slug);
    if ($record[0] === 'flow') $assert(str_contains($html, 'absolute') && str_contains($html, 'mud flow meter'), 'Flow query intent and measurement boundary');
    if ($record[0] === 'density') $assert(str_contains($html, 'shale sample density'), 'Density measurand boundary');
    if ($record[0] === 'conductivity') $assert(str_contains($html, 'compensation') && str_contains($html, 'resistivity'), 'Conductivity configuration boundary');
    if (str_starts_with($record[0], 'level_')) $assert(str_contains($html, 'tank geometry') && str_contains($html, 'order code'), 'Level and volume boundary');
    if ($record[0] === 'pressure') $assert(str_contains($html, 'assembly pressure rating') && str_contains($hero, $record[3]), 'Pressure range and source conflict boundary');
    if ($record[0] === 'torque') $assert(str_contains($html, 'Current-to-torque') && str_contains($html, 'motor/drive'), 'Current versus torque boundary');
    if ($record[0] === 'depth') $assert(str_contains($html, 'pulses per revolution') && str_contains($html, 'absolute well depth') && str_contains($hero, $record[3]), 'Encoder resolution and depth boundary');
    if (str_starts_with($slug, 'wireless-')) $assert(str_contains($html, 'Wireless system boundary'), 'Wireless receiver boundary');
}
foreach (['unrelated-sensor', 'mud-temperature-sensor-ar'] as $slug) {
    $id = wp_insert_post(['post_type' => 'cml_product', 'post_status' => 'publish', 'post_title' => 'Unrelated', 'post_name' => $slug]);
    $GLOBALS['wp_query'] = new WP_Query(['post_type' => 'cml_product', 'p' => $id]);
    $assert(cml_sensor_context() === [], 'Unrelated/localized record unchanged');
    $assert(apply_filters('render_block_core/post-content', 'untouched') === 'untouched', 'Non-target body unchanged');
}
$report = ['isolatedWordPress' => true, 'assertions' => $passed, 'products' => count(cml_sensor_records()), 'passed' => true];
file_put_contents('/cml-test-results/sensor-integration.json', wp_json_encode($report, JSON_PRETTY_PRINT));
echo wp_json_encode($report);
