<?php
/** Read-only integration assertions, run using wp eval-file before publishing the MU file. */
if (!defined('WP_CLI') || !WP_CLI) exit;
if (!function_exists('cml_buyer_context')) require_once getenv('HOME') . '/tmp/cml-buyer-guide-candidate.php';
$passed = 0;
$assert = function ($condition, $label) use (&$passed) { if (!$condition) WP_CLI::error($label); $passed++; };
$ids = get_option('cml_pages', []);
$GLOBALS['wp_query'] = new WP_Query(['page_id' => $ids['en']['products']]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$_GET = [];
$assert(cml_buyer_context() === 'catalogue', 'English catalogue context');
$html = apply_filters('render_block_cml/catalogue', '<div class="catalogue-fixture">Equipment</div>');
$assert(str_contains($html, 'Equipment selection comparison'), 'Catalogue decision content');
$assert(str_contains($html, 'catalogue-fixture'), 'Original listing retained');
$assert(str_contains($html, 'product=Mud%20logging%20equipment%20selection'), 'Prefilled catalogue RFQ');
$assert(!str_contains($html, 'wp-json') && !str_contains($html, '<script'), 'No extra form or tracking client');
foreach ([['q' => 'pressure'], ['q' => '1'], ['family' => 'mud-logging-units'], ['q' => ['bad']], ['catalog_page' => '2']] as $query) {
    $_GET = $query;
    $assert(cml_buyer_context() === '', 'Filtered/paginated scope exclusion');
}
$_GET = [];
$GLOBALS['wp_query'] = new WP_Query(['page_id' => $ids['ar']['products']]);
$assert(cml_buyer_context() === '', 'Non-English catalogue not overwritten');
$GLOBALS['wp_query'] = new WP_Query(['cml_family' => 'mud-logging-units']);
$assert(cml_buyer_context() === 'units', 'Unit family context');
$html = apply_filters('render_block_cml/catalogue', '<div class="unit-fixture">Unit listing</div>');
$assert(str_contains($html, 'unit-fixture') && str_contains($html, 'RFQ checklist'), 'Unit listing and decision content');
$assert(str_contains($html, '/product/sls-mud-logging-unit/'), 'Real system link');
$assert(str_contains(apply_filters('wpseo_title', 'old'), 'Mud Logging Units & Surface'), 'Unit metadata');
$GLOBALS['wp_query'] = new WP_Query(['cml_family' => 'mud-logging-sensors']);
$assert(cml_buyer_context() === '', 'Other family not overwritten');
WP_CLI::success($passed . ' read-only buying guide assertions passed.');
