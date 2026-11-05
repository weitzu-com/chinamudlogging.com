<?php
/** Apply the approved Field / Industrial presentation without changing Core behavior. */
defined('ABSPATH') || exit;

function cml_field_inner_kind(): string {
    if (is_singular('cml_product')) return 'product';
    if (is_tax('cml_family')) return 'catalogue';
    if (!is_page()) return '';
    $post = get_post();
    if (has_block('cml/catalogue', $post)) return 'catalogue';
    if (has_block('cml/inquiry', $post)) return 'inquiry';
    return '';
}

add_filter('body_class', function (array $classes): array {
    $kind = cml_field_inner_kind();
    if ($kind) {
        $classes[] = 'cml-field-inner';
        $classes[] = 'cml-field-' . $kind;
    }
    return $classes;
});

add_action('wp_enqueue_scripts', function () {
    if (!cml_field_inner_kind()) return;
    wp_enqueue_style('cml-field-inner', get_theme_file_uri('assets/field-inner.css'), ['cml-site'], filemtime(get_theme_file_path('assets/field-inner.css')));
}, 40);
