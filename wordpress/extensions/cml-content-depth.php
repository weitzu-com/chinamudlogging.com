<?php
/**
 * Plugin Name: ChinaMudLogging Content Depth
 * Description: Source-bounded category decisions, component roles and missing-document checklists.
 * Version: 1.0.0
 * Requires PHP: 8.2
 */
defined('ABSPATH') || exit;
const CML_DEPTH_REVISION = '2026-11-05T06:40:55+00:00';
function cml_depth_copy(): array {
    static $data;
    return $data ??= json_decode(file_get_contents(__DIR__ . '/cml-content-depth.json'), true, 512, JSON_THROW_ON_ERROR);
}
function cml_depth_profile(WP_Post $post): array {
    if ($post->post_type !== 'cml_product' || !function_exists('cml_priority_language') || cml_priority_language($post) !== 'en') return [];
    $sku = get_post_meta($post->ID, '_cml_sku', true);
    if (!is_string($sku) || trim($sku) === '') return [];
    $source = ''; $name = '';
    $rows = get_post_meta($post->ID, '_cml_specifications', true);
    foreach (is_array($rows) ? $rows : [] as $row) {
        if (!is_array($row) || !is_string($row['raw'] ?? null)) continue;
        if (($row['label'] ?? '') === 'Source URL') $source = rtrim(trim($row['raw']), '.');
        if (($row['label'] ?? '') === 'Name') $name = $row['raw'];
    }
    if (!preg_match('~^https://emall\.epec\.com/productd/[0-9]+/[0-9]+$~D', $source)) return [];
    // Existing full-instrument guidance wins; component words are excluded there.
    if (function_exists('cml_priority_sensor') && cml_priority_sensor($post)) return [];
    foreach (cml_depth_copy()['profiles'] as $role => $record) {
        foreach ($record['names'] as $needle) {
            if (str_contains($name, $needle)) return ['role' => $role, 'record' => $record, 'sku' => $sku, 'name' => $name, 'source' => $source];
        }
    }
    return [];
}
function cml_depth_family(): array {
    if (is_admin() || is_feed() || !is_tax('cml_family') || is_paged() || !function_exists('cml_priority_language')) return [];
    foreach (['q', 'family'] as $key) if (isset($_GET[$key]) && (!is_scalar($_GET[$key]) || (string)$_GET[$key] !== '')) return [];
    if (isset($_GET['catalog_page']) && (!is_scalar($_GET['catalog_page']) || !in_array((string)$_GET['catalog_page'], ['', '1'], true))) return [];
    $term = get_queried_object();
    if (!$term instanceof WP_Term || !function_exists('pll_get_term')) return [];
    $lang = cml_priority_language($term); $copy = cml_depth_copy()['languages'][$lang] ?? [];
    if (!$copy) return [];
    $english = get_term(pll_get_term($term->term_id, 'en'), 'cml_family');
    if (!$english instanceof WP_Term || !isset($copy['families'][$english->slug])) return [];
    return ['kind' => 'family', 'lang' => $lang, 'term' => $term, 'family' => $english->slug, 'copy' => $copy];
}
function cml_depth_context(): array {
    if (is_admin() || is_feed() || is_404()) return [];
    if (is_tax('cml_family')) return cml_depth_family();
    $post = get_queried_object();
    if (!$post instanceof WP_Post || !function_exists('cml_priority_language') || cml_priority_language($post) !== 'en') return [];
    if (is_singular('cml_product')) {
        $record = cml_depth_copy()['legacy'][$post->post_name] ?? [];
        if ($record && get_post_meta($post->ID, '_cml_source_id', true) === $record['sourceId']) return ['kind' => 'legacy', 'post' => $post, 'record' => $record];
        $profile = cml_depth_profile($post);
        return $profile ? ['kind' => 'component', 'post' => $post, 'profile' => $profile] : [];
    }
    $article = cml_depth_copy()['article'];
    return is_singular('post') && $post->post_name === $article['slug'] ? ['kind' => 'article', 'post' => $post, 'record' => $article] : [];
}
function cml_depth_plain_title(WP_Post $post): string { return wp_strip_all_tags(html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }
function cml_depth_list(array $rows): string { return '<ul>' . implode('', array_map(fn($text) => '<li>' . esc_html($text) . '</li>', $rows)) . '</ul>'; }
function cml_depth_rfq(string $request, string $label, string $lang = 'en'): string {
    return '<a class="cml-button" href="' . esc_url(add_query_arg('product', rawurlencode($request), cml_page_url('contact', $lang))) . '">' . esc_html($label) . ' →</a>';
}
function cml_depth_family_html(array $c): string {
    $copy = $c['copy'];
    $html = '<section class="cml-depth-guide" id="cml-depth-guide"><h2>' . esc_html($copy['heading']) . '</h2><p>' . esc_html($copy['intro']) . '</p>';
    $html .= '<h3>' . esc_html($copy['familyHeading']) . '</h3>' . cml_depth_list($copy['families'][$c['family']]);
    $html .= '<h3>' . esc_html($copy['documentsHeading']) . '</h3>' . cml_depth_list($copy['documents']);
    $html .= '<h3>' . esc_html($copy['rfqHeading']) . '</h3>' . cml_depth_list($copy['rfq']);
    $html .= '<p><a href="' . esc_url(home_url('/product-category/' . $c['family'] . '/')) . '" hreflang="en">' . esc_html($copy['english']) . ' →</a></p>';
    return $html . cml_depth_rfq($c['term']->name . ' — ' . $copy['request'], $copy['button'], $c['lang']) . '</section>';
}
function cml_depth_component_html(array $c): string {
    $p = $c['profile']; $r = $p['record'];
    $html = '<section class="cml-depth-guide" id="cml-depth-guide"><h2>' . esc_html($r['title']) . '</h2><p>' . esc_html($r['scope']) . '</p><dl><dt>Retained catalogue designation</dt><dd><bdi>' . esc_html($p['sku']) . '</bdi></dd><dt>Retained source name</dt><dd>' . esc_html($p['name']) . '</dd></dl>';
    $html .= '<p><a href="' . esc_url(home_url('/product/mud-logging-sensors/#cml-depth-role-' . $p['role'])) . '">Read the selection checklist for this component role</a></p><h3>Documents and details to confirm for this item</h3>' . cml_depth_list($r['missing']);
    $html .= '<p>The items above are a request checklist, not confirmed documents or specifications for this catalogue record. Use the exact source reference to distinguish entries that share a short designation.</p><p><a href="' . esc_url($p['source']) . '" target="_blank" rel="noopener noreferrer">Supplier catalogue reference</a> · <a href="' . esc_url(home_url('/product-category/mud-logging-sensors/')) . '">Measurement and interface guide</a></p>';
    return $html . cml_depth_rfq(cml_depth_plain_title($c['post']) . ' — catalogue designation: ' . $p['sku'], 'Request the exact item and matched documents') . '</section>';
}
function cml_depth_record_html(array $c): string {
    $r = $c['record'];
    $html = '<section class="cml-depth-guide" id="cml-depth-guide"><h2>' . esc_html($r['title']) . '</h2><p>' . esc_html($r['intro']) . '</p>';
    if ($c['kind'] === 'article') {
        foreach ($r['sections'] as $section) $html .= '<h3>' . esc_html($section['heading']) . '</h3>' . cml_depth_list($section['items']);
        $html .= '<p><a href="' . esc_url(home_url('/product/mud-logging-unit/')) . '">Define a unit configuration</a> · <a href="' . esc_url(home_url('/product/surface-logging-systems/')) . '">Define a system project</a></p>';
    } else {
        $html .= '<h3>Identify the actual requirement</h3>' . cml_depth_list($r['checks']) . '<h3>Still to confirm for an offer</h3>' . cml_depth_list($r['missing']);
        foreach ($r['links'] as $path => $label) $html .= '<p><a href="' . esc_url(home_url($path)) . '">' . esc_html($label) . '</a></p>';
        if ($c['post']->post_name === 'mud-logging-sensors') {
            $html .= '<h3>Sensor and component role checklists</h3><p>Select the actual item role below. These buying checks do not establish a model specification or interchangeability.</p>';
            foreach (cml_depth_copy()['profiles'] as $role => $profile) $html .= '<details id="cml-depth-role-' . esc_attr($role) . '"><summary>' . esc_html($profile['title']) . '</summary>' . cml_depth_list($profile['checks']) . '</details>';
        }
    }
    return $html . cml_depth_rfq($r['title'] . ' — identification and configuration review', 'Discuss the equipment requirement') . '</section>';
}
add_filter('render_block_cml/catalogue', function ($html) { $c = cml_depth_family(); return $c ? cml_depth_family_html($c) . $html : $html; }, 120);
add_filter('render_block_core/post-content', function ($html) {
    $c = cml_depth_context();
    if (($c['kind'] ?? '') === 'component') return cml_depth_component_html($c) . '<details class="cml-depth-source"><summary>Retained catalogue record</summary>' . $html . '</details>';
    if (in_array($c['kind'] ?? '', ['legacy', 'article'], true)) return cml_depth_record_html($c) . '<details class="cml-depth-source"><summary>Retained earlier source text</summary>' . $html . '</details>';
    return $html;
}, 120);
add_filter('wpseo_metadesc', function ($original) {
    $c = cml_depth_context();
    if (($c['kind'] ?? '') === 'article') return $c['record']['description'];
    if (($c['kind'] ?? '') === 'legacy') return $c['record']['intro'];
    if (($c['kind'] ?? '') === 'component') return cml_depth_plain_title($c['post']) . ': confirm the exact item, installation interfaces and model-matched documents before replacement.';
    return $original;
}, 120);
foreach (['wpseo_title', 'pre_get_document_title'] as $hook) add_filter($hook, function ($original) { $c = cml_depth_context(); return ($c['kind'] ?? '') === 'legacy' ? $c['record']['title'] . ' | ChinaMudLogging.com' : $original; }, 120);
add_action('wp_enqueue_scripts', function () {
    if (!cml_depth_context()) return;
    wp_register_style('cml-content-depth', false, [], '1.0.0'); wp_enqueue_style('cml-content-depth');
    wp_add_inline_style('cml-content-depth', '.cml-depth-guide{margin-block:2rem;padding:clamp(1rem,3vw,2rem);border:1px solid #ccd6dc;border-radius:12px;background:#f4f8f8;overflow-wrap:anywhere}.cml-depth-guide li{margin-block:.7rem}.cml-depth-guide dd{margin-inline-start:0}.cml-depth-source summary,.cml-depth-guide summary{cursor:pointer;min-height:44px;padding-block:.7rem;font-weight:600}');
});
function cml_depth_modified($original): string { return is_string($original) && strtotime($original) >= strtotime(CML_DEPTH_REVISION) ? $original : CML_DEPTH_REVISION; }
add_filter('wpseo_schema_webpage', function ($data) { if (cml_depth_context()) $data['dateModified'] = cml_depth_modified($data['dateModified'] ?? ''); return $data; }, 120);
add_filter('wpseo_sitemap_entry', function ($entry, $type, $object) {
    if (!is_array($entry)) return $entry;
    $changed = false;
    if ($type === 'post' && $object instanceof WP_Post && $object->post_status === 'publish' && function_exists('cml_priority_language') && cml_priority_language($object) === 'en') {
        $legacy = cml_depth_copy()['legacy'][$object->post_name] ?? [];
        $changed = ($object->post_type === 'cml_product' && (($legacy && get_post_meta($object->ID, '_cml_source_id', true) === $legacy['sourceId']) || cml_depth_profile($object))) || ($object->post_type === 'post' && $object->post_name === cml_depth_copy()['article']['slug']);
    } elseif ($type === 'term' && $object instanceof WP_Term && $object->taxonomy === 'cml_family' && function_exists('cml_priority_language') && function_exists('pll_get_term')) {
        $lang = cml_priority_language($object); $en = get_term(pll_get_term($object->term_id, 'en'), 'cml_family');
        $changed = $en instanceof WP_Term && isset(cml_depth_copy()['languages'][$lang]['families'][$en->slug]);
    }
    if ($changed) $entry['mod'] = cml_depth_modified($entry['mod'] ?? '');
    return $entry;
}, 120, 3);
add_filter('wpseo_sitemap_index_links', function ($links) {
    foreach ($links as &$link) if (preg_match('~/(?:cml_product|cml_family|post)-sitemap[0-9]*\.xml$~', wp_parse_url($link['loc'] ?? '', PHP_URL_PATH) ?: '')) $link['lastmod'] = cml_depth_modified($link['lastmod'] ?? '');
    unset($link); return $links;
}, 120);
