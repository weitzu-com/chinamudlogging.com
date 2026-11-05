<?php
if (!defined('CML_SEARCH_ISOLATED_TEST') || !CML_SEARCH_ISOLATED_TEST) exit;
require_once '/wordpress/wp-load.php';
$count = 0;
$assert = function ($ok, $label) use (&$count) {
    if (!$ok) throw new RuntimeException($label);
    $count++;
};
$query = function (array $args) {
    $GLOBALS['wp_query'] = new WP_Query($args);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
};
$old = '2026-11-01T00:00:00+00:00';
$new = '2026-11-06T12:00:00+00:00';
$page_data = ['@type' => ['WebPage', 'FAQPage'], '@id' => 'https://example.test/#webpage', 'dateModified' => $old, 'breadcrumb' => ['@id' => '#breadcrumb']];
foreach (['en', 'zh', 'ar', 'es', 'ru', 'fr', 'de'] as $language) {
    $id = wp_insert_post(['post_type' => 'cml_product', 'post_status' => 'publish', 'post_name' => 'sensor-' . $language, 'post_title' => 'Sensor ' . $language, 'post_content' => 'SOURCE RECORD UNCHANGED']);
    $query(['post_type' => 'cml_product', 'p' => $id]);
    $schema = apply_filters('wpseo_schema_webpage', $page_data);
    $assert(in_array('ItemPage', $schema['@type'], true) && in_array('FAQPage', $schema['@type'], true) && !in_array('WebPage', $schema['@type'], true), 'Catalogue page schema keeps other page types');
    $assert($schema['@id'] === $page_data['@id'] && $schema['breadcrumb'] === $page_data['breadcrumb'], 'Existing graph identity and breadcrumb preserved');
    $entry = ['loc' => get_permalink($id), 'mod' => $old, 'images' => [['src' => 'https://example.test/image.jpg']]];
    $updated = apply_filters('wpseo_sitemap_entry', $entry, 'post', get_post($id));
    $assert($updated['loc'] === $entry['loc'] && $updated['images'] === $entry['images'] && $updated['mod'] === $schema['dateModified'], 'Sitemap and graph agree without changing URLs or images');
    ob_start(); do_action('wp_head'); $head = ob_get_clean();
    preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $head, $matches);
    $assert(count($matches[1]) === 1, 'Single fallback graph when Yoast absent');
    $fallback = json_decode($matches[1][0], true, 512, JSON_THROW_ON_ERROR);
    $assert($fallback['@type'] === 'ItemPage' && !str_contains($head, '"@type":"Product"'), 'Rendered head has no ineligible Product candidate');
    $assert(!array_intersect_key($fallback, array_flip(['offers', 'review', 'aggregateRating'])) && !str_contains($head, '"price"'), 'No invented offer or rating');
    $assert(get_post($id)->post_content === 'SOURCE RECORD UNCHANGED', 'No body or database mutation');
}
foreach (['drilling-monitoring', 'formation-evaluation', 'mud-logging-sensors', 'drilling-monitoring-ar', 'mud-logging-units'] as $slug) {
    $term = wp_insert_term($slug, 'cml_family', ['slug' => $slug]);
    $query(['cml_family' => $slug]);
    $target = in_array($slug, ['drilling-monitoring', 'formation-evaluation', 'mud-logging-sensors'], true);
    $schema = apply_filters('wpseo_schema_webpage', $page_data);
    $entry = ['loc' => 'https://example.test/product-category/' . $slug . '/', 'mod' => $old];
    $updated = apply_filters('wpseo_sitemap_entry', $entry, 'term', get_term($term['term_id']));
    $assert($target ? $schema['dateModified'] === CML_CATALOGUE_SCHEMA_REVISION : $schema === $page_data, 'Only three selection family graphs revised');
    $assert($target ? $updated['mod'] === $schema['dateModified'] : $updated === $entry, 'Localized and other families unchanged');
}
$page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Contact']);
$query(['page_id' => $page]);
$assert(apply_filters('wpseo_schema_webpage', $page_data) === $page_data, 'Contact and ordinary page graph unchanged');
$entry = ['loc' => get_permalink($page), 'mod' => $old];
$assert(apply_filters('wpseo_sitemap_entry', $entry, 'post', get_post($page)) === $entry, 'Ordinary sitemap entry unchanged');
$assert(apply_filters('wpseo_sitemap_entry', false, 'post', get_post($page)) === false, 'Excluded URL stays excluded');
$assert(apply_filters('wpseo_sitemap_entry', [], 'post', get_post($page)) === [], 'Empty URL not revived');
$draft = new WP_Post((object) ['ID' => 0, 'post_type' => 'cml_product', 'post_status' => 'draft']);
$assert(apply_filters('wpseo_sitemap_entry', $entry, 'post', $draft) === $entry, 'Draft not assigned public revision');
$assert(cml_search_latest_revision($new) === $new, 'Later editorial revision retained');
$assert(cml_search_latest_revision($old) === cml_search_latest_revision($old), 'Fixed revision never tracks request time');
$links = [['loc' => 'https://example.test/cml_product-sitemap2.xml', 'lastmod' => $old], ['loc' => 'https://example.test/cml_family-sitemap.xml', 'lastmod' => $new], ['loc' => 'https://example.test/page-sitemap.xml', 'lastmod' => $old]];
$updated = apply_filters('wpseo_sitemap_index_links', $links);
$assert($updated[0]['lastmod'] === CML_CATALOGUE_SCHEMA_REVISION && $updated[1]['lastmod'] === $new && $updated[2] === $links[2], 'Sitemap index scope and later modification preserved');
$query(['post_type' => 'cml_product', 'p' => $id]);
define('WPSEO_VERSION', 'isolated-test');
ob_start(); cml_search_fallback_schema(); $head = ob_get_clean();
$assert($head === '', 'No duplicate fallback with Yoast enabled');
$report = ['isolatedWordPress' => true, 'passed' => true, 'assertions' => $count, 'languages' => 7, 'revision' => CML_CATALOGUE_SCHEMA_REVISION];
file_put_contents('/cml-test-results/search-quality.json', wp_json_encode($report, JSON_PRETTY_PRINT));
echo wp_json_encode($report);
