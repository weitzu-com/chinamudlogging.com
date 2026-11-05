<?php
defined('ABSPATH') || exit;

// Fixed source revision, not request time. Change only when rendered schema changes.
const CML_CATALOGUE_SCHEMA_REVISION = '2026-11-05T04:14:09+00:00';

function cml_search_latest_revision($original): string {
    $original = is_string($original) ? $original : '';
    return $original !== '' && strtotime($original) >= strtotime(CML_CATALOGUE_SCHEMA_REVISION)
        ? $original : CML_CATALOGUE_SCHEMA_REVISION;
}

function cml_search_selection_family($object): bool {
    return $object instanceof WP_Term && $object->taxonomy === 'cml_family'
        && in_array($object->slug, ['drilling-monitoring', 'formation-evaluation', 'mud-logging-sensors'], true);
}

// RFQ-only records have no verified public offer or customer rating. Describe the
// catalogue page without emitting an ineligible Product rich-result candidate.
function cml_search_webpage_schema(array $data): array {
    if (is_singular('cml_product')) {
        $types = (array) ($data['@type'] ?? 'WebPage');
        $data['@type'] = array_values(array_unique(array_merge(array_diff($types, ['WebPage']), ['ItemPage'])));
        $data['dateModified'] = cml_search_latest_revision($data['dateModified'] ?? '');
    } elseif (cml_search_selection_family(get_queried_object())) {
        $data['dateModified'] = cml_search_latest_revision($data['dateModified'] ?? '');
    }
    return $data;
}
add_filter('wpseo_schema_webpage', 'cml_search_webpage_schema');

function cml_search_fallback_schema(): void {
    if (defined('WPSEO_VERSION') || !is_singular('cml_product')) return;
    $id = get_queried_object_id();
    $schema = ['@context' => 'https://schema.org', '@type' => 'ItemPage',
        '@id' => get_permalink($id) . '#webpage', 'url' => get_permalink($id),
        'name' => get_the_title($id), 'description' => get_the_excerpt($id),
        'inLanguage' => cml_language(),
        'dateModified' => cml_search_latest_revision(get_post_modified_time('c', true, $id))];
    if (has_post_thumbnail($id)) $schema['primaryImageOfPage'] = get_the_post_thumbnail_url($id, 'full');
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
}
add_action('wp_head', 'cml_search_fallback_schema');

function cml_search_sitemap_entry($entry, string $type, $object) {
    if (!is_array($entry) || empty($entry['loc'])) return $entry;
    $product = $type === 'post' && $object instanceof WP_Post
        && $object->post_type === 'cml_product' && $object->post_status === 'publish';
    $family = $type === 'term' && cml_search_selection_family($object);
    if ($product || $family) $entry['mod'] = cml_search_latest_revision($entry['mod'] ?? '');
    return $entry;
}
add_filter('wpseo_sitemap_entry', 'cml_search_sitemap_entry', 30, 3);

function cml_search_sitemap_index(array $links): array {
    foreach ($links as &$link) {
        $path = wp_parse_url($link['loc'] ?? '', PHP_URL_PATH);
        if (is_string($path) && preg_match('~/cml_(?:product|family)-sitemap[0-9]*\.xml$~', $path)) {
            $link['lastmod'] = cml_search_latest_revision($link['lastmod'] ?? '');
        }
    }
    unset($link);
    return $links;
}
add_filter('wpseo_sitemap_index_links', 'cml_search_sitemap_index');
