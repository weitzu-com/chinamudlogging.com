<?php
/** Read-only acceptance inventory. Run with wp eval-file on the target site. */
if (!defined('ABSPATH')) throw new RuntimeException('WordPress required.');
global $wpdb;
$counts = [];
foreach (['cml_product', 'post', 'page'] as $type) {
    $counts[$type] = [];
    foreach (['publish', 'draft'] as $status) {
        $counts[$type][$status] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_cml_import_key' WHERE p.post_type=%s AND p.post_status=%s", $type, $status));
    }
}
$report = [
    'home' => get_option('home'), 'wordpress' => get_bloginfo('version'), 'php' => PHP_VERSION,
    'polylang' => defined('POLYLANG_VERSION') ? POLYLANG_VERSION : null,
    'theme' => get_stylesheet(), 'counts' => $counts,
    'importedMedia' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_cml_asset_key'"),
    'languages' => function_exists('pll_languages_list') ? pll_languages_list() : [],
    'blogPublic' => get_option('blog_public'), 'mailEnabled' => (bool)get_option('cml_mail_enabled', false),
    'stagingGuard' => is_file(WPMU_PLUGIN_DIR . '/cml-staging-guard.php'),
    'inquiryCount' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cml_inquiries"),
    'importDigest' => get_option('cml_import_digest'),
    'urls' => array_map(fn($post) => ['url' => get_permalink($post), 'language' => pll_get_post_language($post->ID), 'type' => $post->post_type], get_posts(['post_type' => ['page','post','cml_product'], 'post_status' => 'publish', 'numberposts' => -1, 'lang' => '', 'meta_key' => '_cml_import_key'])),
];
$report['structuredSpecificationRows'] = 0;
$report['structuredUnitRows'] = 0;
foreach (get_posts(['post_type' => 'cml_product', 'post_status' => 'publish', 'numberposts' => -1, 'lang' => '']) as $post) {
    foreach (get_post_meta($post->ID, '_cml_specifications', true) ?: [] as $row) {
        $report['structuredSpecificationRows']++;
        if ($row['unit'] !== '') $report['structuredUnitRows']++;
    }
}
echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
