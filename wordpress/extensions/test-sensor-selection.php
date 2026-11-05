<?php
/** Isolated WordPress integration test. Never run the fixture seeding on production. */
if (!defined('CML_SENSOR_ISOLATED_TEST') || !CML_SENSOR_ISOLATED_TEST) exit;
require_once '/wordpress/wp-load.php';
require_once '/cml/extensions/cml-sensor-selection.php';
require_once '/cml/extensions/cml-buyer-guide.php';
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
    if ($record[0] === 'hook') $assert(str_contains($html, 'pressure-to-hookload') && str_contains($html, 'load capacity'), 'Hookload conversion boundary');
    if ($record[0] === 'gas') $assert(str_contains($html, 'end-to-end latency') && str_contains($html, 'https://www.osha.gov/hydrogen-sulfide/evaluating-controlling-exposure') && str_contains($html, 'not a product manual'), 'Gas alarm boundary and general reference');
    if ($record[0] === 'wireless_master') $assert(str_contains($html, 'capacity conflict') && str_contains($html, 'board-level') && str_contains($html, 'stale-data'), 'Master capacity and signalling boundary');
    if ($record[0] === 'wireless_system') $assert(str_contains($html, 'WITS variant') && str_contains($html, 'data-quality'), 'Wireless mapping boundary');
    if ($record[0] === 'power') $assert(str_contains($html, 'chemistry') && str_contains($html, 'DIY') && str_contains($html, 'matching voltage alone'), 'Power pairing boundary');
    if ($record[0] === 'degasser') $assert(str_contains($html, 'tubing degasser RFQ') && str_contains($html, 'not a gas analyser'), 'Degasser duty and analysis boundary');
    if ($record[0] === 'fluoroscope') $assert(str_contains($html, 'quantitative oil content') && str_contains($html, 'Legacy solvent'), 'Fluoroscope observation and procedure boundary');
    if ($record[0] === 'display') $assert(str_contains($html, 'exact supplied assembly') && str_contains($html, 'HDMI, VGA') && str_contains($html, 'certificate with schedule'), 'Display interface and approval boundary');
    if ($record[0] === 'computer') $assert(str_contains($html, 'bill of materials') && str_contains($html, 'licence') && str_contains($html, 'D-Sub opening'), 'Computer chassis and licensed configuration boundary');
    if ($record[0] === 'monitoring_project') $assert(str_contains($html, 'channel list') && str_contains($html, 'missing/stale-data'), 'Monitoring scope and data boundary');
    if ($record[0] === 'evaluation_project') $assert(str_contains($html, 'downhole') && str_contains($html, 'analytical result'), 'Evaluation workflow and method boundary');
    if (str_starts_with($slug, 'wireless-')) $assert(str_contains($html, 'Wireless system boundary'), 'Wireless receiver boundary');
}
foreach (['unrelated-sensor', 'mud-temperature-sensor-ar'] as $slug) {
    $id = wp_insert_post(['post_type' => 'cml_product', 'post_status' => 'publish', 'post_title' => 'Unrelated', 'post_name' => $slug]);
    $GLOBALS['wp_query'] = new WP_Query(['post_type' => 'cml_product', 'p' => $id]);
    $assert(cml_sensor_context() === [], 'Unrelated/localized record unchanged');
    $assert(apply_filters('render_block_core/post-content', 'untouched') === 'untouched', 'Non-target body unchanged');
}
$set_query = function (array $args) {
    $GLOBALS['wp_query'] = new WP_Query($args);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
};
$listing = '<h1>ORIGINAL FAMILY</h1><form class="existing-filter"><input name="q"></form><article>LISTING-SENTINEL</article>';
foreach (cml_system_hub_records() as $slug => $record) {
    $term = wp_insert_term($record[2], 'cml_family', ['slug' => $slug]);
    $pid = wp_insert_post(['post_type' => 'cml_product', 'post_status' => 'publish', 'post_title' => 'Hub fixture', 'post_name' => 'hub-fixture-' . $slug]);
    wp_set_object_terms($pid, $term['term_id'], 'cml_family');
    $_GET = []; $set_query(['cml_family' => $slug]);
    $c = cml_sensor_context();
    $assert(($c['scope'] ?? '') === 'hub' && $c['slug'] === $slug, 'Exact hub scope');
    $assert(str_contains(apply_filters('wpseo_title', 'old'), $record[1]), 'Distinct hub metadata');
    $html = apply_filters('render_block_cml/catalogue', $listing);
    $assert(str_contains($html, $listing) && substr_count($html, '<form') === 1, 'Original listing and only existing search form');
    $assert(substr_count($html, '<h1>') === 1, 'Original archive heading preserved');
    $assert(substr_count($html, 'id="cml-sensor-heading"') === 1, 'Single hub shortcut block');
    $assert(substr_count($html, 'id="cml-sensor-compatibility"') === 1, 'Single hub decision block');
    $assert(str_contains($html, 'product=' . rawurlencode($record[2])), 'Hub-specific RFQ context');
    $assert(apply_filters('render_block_core/post-content', 'post untouched') === 'post untouched' && apply_filters('render_block_cml/product', 'hero untouched') === 'hero untouched', 'Archive does not inject into individual card/post blocks');
    $assert(!str_contains($html, '<script') && !preg_match('/href="[^"]*\.pdf/i', $html) && str_contains($html, 'not a set of available downloads'), 'Hub document and analytics boundary');
    foreach ([['q' => 'pressure'], ['family' => 'drilling-monitoring'], ['q' => ['bad']], ['family' => ['bad']], ['catalog_page' => '2'], ['catalog_page' => ['bad']]] as $query) {
        $_GET = $query;
        $assert(cml_sensor_context() === [], 'Filtered/invalid archive context excluded');
        $assert(apply_filters('render_block_cml/catalogue', $listing) === $listing, 'Filtered archive listing unchanged');
    }
    $_GET = []; $set_query(['cml_family' => $slug, 'paged' => 2]);
    $assert(cml_sensor_context() === [], 'Actual archive pagination excluded');
    $assert(apply_filters('render_block_cml/catalogue', $listing) === $listing, 'Paged archive listing unchanged');
}
$_GET = [];
foreach (['mud-logging-units', 'mud-logging-sensors', 'formation-evaluation-ar'] as $slug) {
    wp_insert_term($slug, 'cml_family', ['slug' => $slug]);
    $set_query(['cml_family' => $slug]);
    $assert(cml_sensor_context() === [], 'Other/localized family excluded');
}
$set_query(['cml_family' => 'mud-logging-units']);
$html = apply_filters('render_block_cml/catalogue', $listing);
$assert(cml_buyer_context() === 'units' && str_contains($html, 'cml-buyer-heading') && !str_contains($html, 'cml-sensor-heading'), 'Existing unit guide retained');
$set_query(['page_id' => $catalogue]);
$html = apply_filters('render_block_cml/catalogue', $listing);
$assert(cml_buyer_context() === 'catalogue' && cml_sensor_context() === [] && str_contains($html, 'cml-buyer-heading') && !str_contains($html, 'cml-sensor-heading'), 'Existing catalogue guide retained');
$report = ['isolatedWordPress' => true, 'assertions' => $passed, 'products' => count(cml_sensor_records()), 'hubs' => count(cml_system_hub_records()), 'passed' => true];
file_put_contents('/cml-test-results/sensor-integration.json', wp_json_encode($report, JSON_PRETTY_PRINT));
echo wp_json_encode($report);
