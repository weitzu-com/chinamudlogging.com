<?php
/** Disposable WordPress fixtures only. Never run against production. */
if (!defined('CML_PRIORITY_ISOLATED_TEST') || !CML_PRIORITY_ISOLATED_TEST) throw new RuntimeException('Isolated test only');
require_once '/wordpress/wp-load.php';
function pll_get_post_language($id, $field = 'slug') { return get_post_meta($id, '_test_lang', true) ?: 'en'; }
function pll_get_term_language($id, $field = 'slug') { return get_term_meta($id, '_test_lang', true) ?: 'en'; }
function pll_get_term($id, $lang) { return get_term_meta($id, '_test_en', true) ?: $id; }
require '/cml/extensions/cml-buyer-guide.php';
if (is_file('/cml/extensions/cml-sensor-selection.php')) require '/cml/extensions/cml-sensor-selection.php';
require '/cml/extensions/cml-seo-priority.php';
$n = 0;
$assert = function ($value, $message) use (&$n) { $n++; if (!$value) throw new RuntimeException($message); };
$set = function ($args) { $GLOBALS['wp_query'] = new WP_Query($args); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query']; };
$make = function ($slug, $type = 'cml_product', $lang = 'en') {
    $id = wp_insert_post(['post_type' => $type, 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $slug, 'post_excerpt' => 'Old excerpt', 'post_content' => '<p>SOURCE-SENTINEL 10ppm Windows 2000 / XP</p>']);
    update_post_meta($id, '_test_lang', $lang); return $id;
};
$copy = cml_priority_copy();
foreach ($copy['equipment'] as $slug => $record) {
    $id = $make($slug); $set(['post_type' => 'cml_product', 'p' => $id]);
    $source = get_post_field('post_content', $id);
    $output = apply_filters('render_block_core/post-content', $source);
    $assert(substr_count($output, $source) === 1, 'Exact retained equipment source');
    $assert(str_contains($output, 'Historical catalogue record') && str_contains($output, $record['heading']), 'Guide and historical boundary');
    $assert(get_post_field('post_content', $id) === $source, 'No stored-content mutation');
    $assert(get_the_excerpt($id) === $record['intro'], 'Buyer-focused excerpt');
    $assert(apply_filters('wpseo_metadesc', 'old') === $record['description'], 'Equipment metadata');
    $assert(!str_contains($output, '<h1') && !str_contains($output, '<form') && !str_contains($output, '<script'), 'Existing heading and form boundaries');
    preg_match_all('/href="([^"]+)"/', $output, $matches); $requests=[]; foreach ($matches[1] as $href) { parse_str(wp_parse_url(html_entity_decode($href), PHP_URL_QUERY) ?: '', $args); if (isset($args['product'])) $requests[]=$args['product']; }
    $assert(in_array($record['request'], $requests, true), 'Product-specific request context');
    $assert(apply_filters('wpseo_schema_webpage', ['dateModified'=>'2026-11-01T00:00:00Z'])['dateModified'] === CML_SEO_PRIORITY_REVISION, 'Changed rendered revision');
}
foreach ($copy['languages'] as $lang => $records) {
    $assert(count(array_unique($records['pages'])) === 7, 'Distinct page intents '.$lang);
    $ids = [];
    foreach ($records['pages'] as $role => $description) { $ids[$role] = $make('fixture-'.$role.'-'.$lang, 'page', $lang); }
    $options = get_option('cml_pages', []); $options[$lang] = $ids; update_option('cml_pages', $options);
    foreach ($ids as $role => $id) {
        $set(['post_type'=>'page','p'=>$id]);
        $assert(apply_filters('wpseo_metadesc','old') === $records['pages'][$role], 'Localized page '.$lang.' '.$role);
        $assert(apply_filters('render_block_core/post-content','sentinel') === 'sentinel', 'No page-body injection');
        if (isset($records['titles'][$role])) $assert(apply_filters('wpseo_title','old') === $records['titles'][$role].' | ChinaMudLogging.com', 'Distinct French page intent');
    }
    foreach ($records['families'] as $slug => $description) {
        $en = get_term_by('slug', $slug, 'cml_family');
        if (!$en) { $row = wp_insert_term($slug, 'cml_family', ['slug'=>$slug]); $en = get_term($row['term_id']); }
        $term = $en;
        if ($lang !== 'en') { $row=wp_insert_term($lang.' '.$slug, 'cml_family', ['slug'=>$slug.'-'.$lang]); $term=get_term($row['term_id']); update_term_meta($term->term_id,'_test_en',$en->term_id); }
        update_term_meta($term->term_id, '_test_lang', $lang);
        $set(['cml_family'=>$term->slug]);
        if ($lang !== 'en' || $slug === 'wireless-sensors') {
            $assert(apply_filters('wpseo_metadesc','old') === $description, 'Localized category '.$lang.' '.$slug);
            $assert(apply_filters('wpseo_title','old') === $term->name.' | ChinaMudLogging.com','Remove archive suffix without title invention');
        }
        $_GET['catalog_page']='2'; $assert(cml_priority_context() === [], 'Pagination excluded'); unset($_GET['catalog_page']);
        $_GET['q']=['bad']; $assert(cml_priority_context() === [], 'Array filter excluded'); unset($_GET['q']);
    }
}
foreach (['超声波液位传感器','电导率传感器','密度传感器','温度传感器','压力传感器','流量传感器','扭矩传感器','泵冲传感器'] as $i => $name) {
    $id=$make('source-model-'.$i); update_post_meta($id,'_cml_sku','原样 0.25-4.6m 3.7V 1%FS');
    update_post_meta($id,'_cml_specifications',[['label'=>'Name','raw'=>$name],['label'=>'Source URL','raw'=>'https://emall.epec.com/productd/141652/15487381.']]);
    $set(['post_type'=>'cml_product','p'=>$id]);$source=get_post_field('post_content',$id);$out=apply_filters('render_block_core/post-content',$source);
    $assert(cml_priority_context()['kind'] === 'sensor', 'Source-qualified sensor');
    $assert(str_contains($out,'原样 0.25-4.6m 3.7V 1%FS') && str_contains($out,$name), 'Verbatim source identity');
    $assert(substr_count($out,$source) === 1 && get_post_field('post_content',$id) === $source,'Source bytes preserved');
    $assert(str_contains($out,'not an independently verified performance specification') && !str_contains($out,'<h1'),'No engineering inference or extra H1');
    $assert(apply_filters('wpseo_metadesc','') !== '', 'Imported sensor description filled');
    preg_match_all('/href="([^"]+)"/', $out, $matches); $requests=[]; foreach ($matches[1] as $href) { parse_str(wp_parse_url(html_entity_decode($href), PHP_URL_QUERY) ?: '', $args); if (isset($args['product'])) { $assert(!preg_match('/%(?![0-9a-fA-F]{2})/', $href), 'No malformed percent escape'); $requests[]=$args['product']; } }
    $assert(in_array(get_the_title($id).' — catalogue designation: 原样 0.25-4.6m 3.7V 1%FS', $requests, true), 'Percent and Unicode designation round-trips exactly');
    update_post_meta($id,'_cml_sku',''); $assert(cml_priority_sensor(get_post($id)) === [], 'Unknown model left out (product 600 boundary)');
    update_post_meta($id,'_cml_sku','known'); update_post_meta($id,'_cml_specifications',[['label'=>'Name','raw'=>$name],['label'=>'Source URL','raw'=>'https://example.com/product']]);
    $assert(cml_priority_sensor(get_post($id)) === [], 'Unapproved source excluded');
}
$id=$make('mud-logging-unit-zh','cml_product','zh'); $set(['post_type'=>'cml_product','p'=>$id]);
$assert(cml_priority_context() === [] && apply_filters('render_block_core/post-content','untouched') === 'untouched','Localized technical translations untouched');
$set(['post_type'=>'cml_product','p'=>$make('unrelated-record')]); $assert(cml_priority_context() === [],'Unrelated product untouched');
file_put_contents('/cml-priority-results/results.json', wp_json_encode(['passed'=>true,'assertions'=>$n,'equipment'=>3,'pageDescriptions'=>49,'familyDescriptions'=>31,'measurementTypes'=>8]));
