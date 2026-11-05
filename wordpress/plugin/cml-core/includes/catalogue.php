<?php
defined('ABSPATH') || exit;

function cml_register_catalogue(): void {
    register_post_type('cml_product', [
        'labels' => ['name' => 'Products', 'singular_name' => 'Product'],
        'public' => true, 'show_in_rest' => true, 'has_archive' => false,
        'rewrite' => ['slug' => 'product', 'with_front' => false],
        'menu_icon' => 'dashicons-products',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
        'template' => [['core/heading', ['level' => 2, 'content' => 'Overview']], ['core/paragraph']],
    ]);
    register_taxonomy('cml_family', 'cml_product', [
        'labels' => ['name' => 'Product families'], 'public' => true,
        'hierarchical' => true, 'show_in_rest' => true,
        'rewrite' => ['slug' => 'product-category', 'with_front' => false],
    ]);
    foreach (['sku', 'weight', 'signal', 'range', 'interface'] as $field) {
        register_post_meta('cml_product', '_cml_' . $field, [
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => fn($allowed, $key, $id) => current_user_can('edit_post', $id),
        ]);
    }
    add_post_type_support('page', 'excerpt');
    register_post_meta('cml_product', '_cml_specifications', [
        'type' => 'array', 'single' => true, 'default' => [],
        'show_in_rest' => ['schema' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => array_fill_keys(['label','raw','value','unit'], ['type' => 'string']), 'additionalProperties' => false]]],
        'sanitize_callback' => 'cml_clean_specifications',
        'auth_callback' => fn($allowed, $key, $id) => current_user_can('edit_post', $id),
    ]);
}
function cml_clean_specifications($rows): array {
    if (!is_array($rows)) return [];
    $clean = [];
    foreach (array_slice($rows, 0, 200) as $row) {
        if (!is_array($row)) continue;
        $item = [];
        foreach (['label','raw','value','unit'] as $key) $item[$key] = html_entity_decode(sanitize_text_field(is_string($row[$key] ?? null) ? $row[$key] : ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($item['label'] !== '' && $item['raw'] !== '') $clean[] = $item;
    }
    return $clean;
}
add_filter('pll_get_post_types', function ($types) { $types['cml_product'] = 'cml_product'; return $types; });
add_filter('pll_get_taxonomies', function ($types) { $types['cml_family'] = 'cml_family'; return $types; });

add_action('add_meta_boxes', function () {
    add_meta_box('cml-fields', 'Product configuration', function ($post) {
        wp_nonce_field('cml_product_fields', 'cml_fields_nonce');
        foreach (['sku' => 'Model / SKU', 'weight' => 'Weight', 'signal' => 'Output signal', 'range' => 'Measurement range', 'interface' => 'System interface'] as $key => $label) {
            echo '<p><label>' . esc_html($label) . '<br><input class="widefat" name="cml_fields[' . esc_attr($key) . ']" value="' . esc_attr(get_post_meta($post->ID, '_cml_' . $key, true)) . '"></label></p>';
        }
        echo '<p>Keep units and model identifiers exactly as confirmed. Leave unverified fields empty. Edit specification tables in the block editor.</p>';
    }, 'cml_product', 'side');
    add_meta_box('cml-specifications', 'Structured source specifications', function ($post) {
        $rows = array_merge(get_post_meta($post->ID, '_cml_specifications', true) ?: [], array_fill(0, 3, []));
        echo '<p>Keep the original value as the reference. Numeric expressions remain text to preserve ranges and precision. Leave value/unit blank when uncertain. Public specification tables are edited in the block editor.</p><table class="widefat"><thead><tr><th>Parameter</th><th>Original value</th><th>Numeric expression</th><th>Unit</th></tr></thead><tbody>';
        foreach ($rows as $index => $row) {
            echo '<tr>';
            foreach (['label','raw','value','unit'] as $key) echo '<td><input class="widefat" aria-label="' . esc_attr($key . ' ' . ($index + 1)) . '" name="cml_specs[' . (int)$index . '][' . esc_attr($key) . ']" value="' . esc_attr($row[$key] ?? '') . '"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }, 'cml_product', 'normal');
});
add_action('save_post_cml_product', function ($id) {
    if (wp_is_post_revision($id) || wp_is_post_autosave($id) || !current_user_can('edit_post', $id)) return;
    if (!isset($_POST['cml_fields_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cml_fields_nonce'])), 'cml_product_fields')) return;
    $fields = isset($_POST['cml_fields']) && is_array($_POST['cml_fields']) ? wp_unslash($_POST['cml_fields']) : [];
    foreach (['sku', 'weight', 'signal', 'range', 'interface'] as $field) {
        update_post_meta($id, '_cml_' . $field, sanitize_text_field(is_scalar($fields[$field] ?? '') ? $fields[$field] ?? '' : ''));
    }
    if (isset($_POST['cml_specs'])) update_post_meta($id, '_cml_specifications', wp_slash(cml_clean_specifications(wp_unslash($_POST['cml_specs']))));
});

function cml_language(): string {
    return function_exists('pll_current_language') ? (pll_current_language('slug') ?: 'en') : 'en';
}
function cml_strings(?string $lang = null): array {
    static $strings;
    $strings ??= json_decode(file_get_contents(CML_CORE_DIR . '/strings.json'), true);
    return $strings[$lang ?: cml_language()] ?? $strings['en'];
}
function cml_t(string $key): string { return cml_strings()[$key] ?? cml_strings('en')[$key] ?? $key; }
function cml_page_url(string $key, ?string $lang = null): string {
    $lang ??= cml_language();
    $ids = get_option('cml_pages', []);
    return isset($ids[$lang][$key]) ? get_permalink($ids[$lang][$key]) : home_url('/');
}
