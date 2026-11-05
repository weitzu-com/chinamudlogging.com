<?php
/**
 * Plugin Name: ChinaMudLogging SEO Priority Repairs
 * Description: Source-preserving equipment guidance and intent-specific multilingual metadata.
 * Version: 1.0.0
 * Requires PHP: 8.2
 */
defined('ABSPATH') || exit;
const CML_SEO_PRIORITY_REVISION = '2026-11-05T05:59:39+00:00';
function cml_priority_copy(): array {
    static $copy;
    return $copy ??= json_decode(file_get_contents(__DIR__ . '/cml-seo-priority.json'), true, 512, JSON_THROW_ON_ERROR);
}
function cml_priority_language($object): string {
    if ($object instanceof WP_Term && function_exists('pll_get_term_language')) return pll_get_term_language($object->term_id, 'slug') ?: '';
    if ($object instanceof WP_Post && function_exists('pll_get_post_language')) return pll_get_post_language($object->ID, 'slug') ?: '';
    return function_exists('cml_language') ? cml_language() : '';
}
function cml_priority_core(): array {
    return cml_priority_copy()['equipment'];
}
function cml_priority_sensor($post): array {
    if (!$post instanceof WP_Post || $post->post_type !== 'cml_product' || cml_priority_language($post) !== 'en') return [];
    $sku = get_post_meta($post->ID, '_cml_sku', true);
    if (!is_string($sku) || trim($sku) === '') return [];
    $rows = get_post_meta($post->ID, '_cml_specifications', true);
    if (!is_array($rows)) return [];
    $source = ''; $name = '';
    foreach ($rows as $row) {
        if (($row['label'] ?? '') === 'Source URL') $source = rtrim(trim($row['raw'] ?? ''), '.');
        if (($row['label'] ?? '') === 'Name') $name = $row['raw'] ?? '';
    }
    if (!preg_match('~^https://emall\.epec\.com/productd/[0-9]+/[0-9]+$~D', $source)) return [];
    foreach (['电路板', '支架', '固定架', '安装架', '模块'] as $component) if (str_contains($name, $component)) return [];
    // Classification uses the source name only, never numbers embedded in an SKU.
    foreach (cml_priority_copy()['measurements'] as $needle => $record) {
        if (str_contains($name, $needle)) return ['sku' => $sku, 'source' => $source, 'name' => $name, 'measurement' => $record];
    }
    return [];
}
function cml_priority_context(): array {
    if (is_admin() || is_feed() || is_404()) return [];
    $object = get_queried_object();
    $lang = cml_priority_language($object);
    $copy = cml_priority_copy();
    if (!isset($copy['languages'][$lang])) return [];
    if (is_singular('cml_product') && $object instanceof WP_Post) {
        if ($lang === 'en' && isset($copy['equipment'][$object->post_name])) return ['kind' => 'equipment', 'post' => $object, 'record' => $copy['equipment'][$object->post_name]];
        $sensor = cml_priority_sensor($object);
        return $sensor ? ['kind' => 'sensor', 'post' => $object, 'record' => $sensor] : [];
    }
    // Search/filter/pagination variants keep their existing metadata and directives.
    foreach (['q', 'family'] as $key) if (isset($_GET[$key]) && (!is_scalar($_GET[$key]) || (string)$_GET[$key] !== '')) return [];
    if (is_paged() || (isset($_GET['catalog_page']) && (!is_scalar($_GET['catalog_page']) || !in_array((string)$_GET['catalog_page'], ['', '1'], true)))) return [];
    if (is_tax('cml_family') && $object instanceof WP_Term && ($lang !== 'en' || $object->slug === 'wireless-sensors')) {
        $english = function_exists('pll_get_term') ? get_term(pll_get_term($object->term_id, 'en'), 'cml_family') : null;
        if (!$english instanceof WP_Term || !isset($copy['languages'][$lang]['families'][$english->slug])) return [];
        return ['kind' => 'family', 'lang' => $lang, 'name' => $object->name, 'description' => $copy['languages'][$lang]['families'][$english->slug]];
    }
    if ($object instanceof WP_Post && is_singular('page')) {
        $ids = get_option('cml_pages', [])[$lang] ?? [];
        $role = array_search($object->ID, array_map('intval', $ids), true);
        if ($role !== false && isset($copy['languages'][$lang]['pages'][$role])) return ['kind' => 'page', 'description' => $copy['languages'][$lang]['pages'][$role], 'title' => $copy['languages'][$lang]['titles'][$role] ?? ''];
    }
    return [];
}
function cml_priority_description($original): string {
    $c = cml_priority_context();
    if (!$c) return (string)$original;
    if (in_array($c['kind'], ['page', 'family'], true)) return $c['description'];
    if ($c['kind'] === 'equipment') return $c['record']['description'];
    return get_the_title($c['post']) . '. Review the catalogue designation and request model-matched installation documents.';
}
add_filter('wpseo_metadesc', 'cml_priority_description', 110);
foreach (['wpseo_title', 'pre_get_document_title'] as $hook) add_filter($hook, function ($original) {
    $c = cml_priority_context();
    if (($c['kind'] ?? '') === 'equipment') return $c['record']['title'] . ' | ChinaMudLogging.com';
    if (($c['kind'] ?? '') === 'family') return $c['name'] . ' | ChinaMudLogging.com';
    if (($c['kind'] ?? '') === 'page' && !empty($c['title'])) return $c['title'] . ' | ChinaMudLogging.com';
    return $original;
}, 110);
add_filter('get_the_excerpt', function ($excerpt, $post) {
    if (is_admin() || !$post instanceof WP_Post || cml_priority_language($post) !== 'en') return $excerpt;
    $record = cml_priority_core()[$post->post_name] ?? [];
    return $post->post_type === 'cml_product' && $record ? $record['intro'] : $excerpt;
}, 110, 2);
function cml_priority_list(array $items): string {
    return '<ul>' . implode('', array_map(fn($text) => '<li>' . esc_html($text) . '</li>', $items)) . '</ul>';
}
function cml_priority_guide(array $c): string {
    $r = $c['record'];
    $html = '<section class="cml-priority-guide" id="cml-priority-guide"><h2>' . esc_html($r['heading']) . '</h2><p>' . esc_html($r['intro']) . '</p>';
    foreach ($r['sections'] as $section) $html .= '<h3>' . esc_html($section['heading']) . '</h3>' . cml_priority_list($section['items']);
    $request = $r['request'];
    $html .= '<p><a class="cml-button" href="' . esc_url(add_query_arg('product', rawurlencode($request), cml_page_url('contact', 'en'))) . '">Request a configuration review →</a></p><nav aria-label="Related procurement routes">';
    foreach ($r['links'] as $path => $label) $html .= '<a href="' . esc_url(home_url($path)) . '">' . esc_html($label) . '</a> ';
    return $html . '</nav></section>';
}
function cml_priority_sensor_guide(array $c): string {
    $r = $c['record']; $m = $r['measurement'];
    $html = '<section class="cml-priority-guide" id="cml-priority-guide"><h2>' . esc_html($m['heading']) . '</h2><p>Use the supplier catalogue designation to identify the requested item, then match its documents to your installation.</p><dl><dt>Catalogue designation — retained verbatim</dt><dd><bdi>' . esc_html($r['sku']) . '</bdi></dd><dt>Source name — retained verbatim</dt><dd>' . esc_html($r['name']) . '</dd></dl><p>The designation is an identification string, not an independently verified performance specification or an approval. Its numeric tokens are not expanded into engineering limits here.</p>';
    $html .= cml_priority_list($m['checks']);
    $html .= '<p>For this exact item, request the current datasheet, drawing, wiring or pinout, and any applicable approval documents. Provide the existing nameplate, required measurement, acquisition input, quantity and destination.</p><p><a href="' . esc_url($r['source']) . '" target="_blank" rel="noopener noreferrer">Supplier catalogue reference</a> · <a href="' . esc_url(home_url('/product-category/mud-logging-sensors/')) . '">Compare measurement and interfaces</a></p>';
    $html .= '<a class="cml-button" href="' . esc_url(add_query_arg('product', rawurlencode(get_the_title($c['post']) . ' — catalogue designation: ' . $r['sku']), cml_page_url('contact', 'en'))) . '">Request this item and its documents →</a>';
    return $html . '</section>';
}
add_filter('render_block_core/post-content', function ($html) {
    $c = cml_priority_context();
    if (($c['kind'] ?? '') === 'equipment') return cml_priority_guide($c) . '<details class="cml-priority-source"><summary>Historical catalogue record — confirm the ordered configuration</summary><p>These retained source statements are not a current supply commitment. Confirm the offered hardware, software support and exact approval documents; do not use this record as operating instructions.</p>' . $html . '</details>';
    if (($c['kind'] ?? '') === 'sensor') return cml_priority_sensor_guide($c) . '<details class="cml-priority-source"><summary>Retained supplier catalogue fields</summary>' . $html . '</details>';
    return $html;
}, 110);
add_action('wp_enqueue_scripts', function () {
    if (!in_array(cml_priority_context()['kind'] ?? '', ['equipment', 'sensor'], true)) return;
    wp_register_style('cml-priority-guide', false, [], '1.0.0'); wp_enqueue_style('cml-priority-guide');
    wp_add_inline_style('cml-priority-guide', '.cml-priority-guide{margin-block:2rem;padding:clamp(1rem,3vw,2rem);background:#f4f8f8;border:1px solid #ccd6dc;border-radius:12px;overflow-wrap:anywhere}.cml-priority-guide nav{display:flex;flex-wrap:wrap;gap:1rem}.cml-priority-guide nav a,.cml-priority-source summary{min-height:44px;padding-block:.7rem}.cml-priority-guide dd{margin-inline-start:0}.cml-priority-source{margin-block:2rem}.cml-priority-source summary{cursor:pointer;font-weight:600}');
});
// Date applies only to changed singular records; no fabricated current publication date.
add_filter('wpseo_schema_webpage', function ($data) {
    $c = cml_priority_context();
    if (in_array($c['kind'] ?? '', ['equipment', 'sensor'], true) && strtotime($data['dateModified'] ?? '') < strtotime(CML_SEO_PRIORITY_REVISION)) $data['dateModified'] = CML_SEO_PRIORITY_REVISION;
    return $data;
}, 110);
add_filter('wpseo_sitemap_entry', function ($entry, $type, $post) {
    if (!is_array($entry) || $type !== 'post' || !$post instanceof WP_Post || $post->post_status !== 'publish' || cml_priority_language($post) !== 'en') return $entry;
    if ((isset(cml_priority_core()[$post->post_name]) || cml_priority_sensor($post)) && strtotime($entry['mod'] ?? '') < strtotime(CML_SEO_PRIORITY_REVISION)) $entry['mod'] = CML_SEO_PRIORITY_REVISION;
    return $entry;
}, 110, 3);

add_filter('wpseo_sitemap_index_links', function ($links) {
    foreach ($links as &$link) {
        if (preg_match('~/cml_product-sitemap[0-9]*\.xml$~', wp_parse_url($link['loc'] ?? '', PHP_URL_PATH) ?: '') && strtotime($link['lastmod'] ?? '') < strtotime(CML_SEO_PRIORITY_REVISION)) $link['lastmod'] = CML_SEO_PRIORITY_REVISION;
    }
    unset($link);
    return $links;
}, 110);
