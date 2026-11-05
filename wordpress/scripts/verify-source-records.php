<?php
/** Read-only comparison against the frozen private migration bundle. */
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('CLI only.');
$bundle = json_decode(file_get_contents(getenv('HOME') . '/.cml-staging/cml-migration/bundle.json'), true);
$failed = []; $dates = 0; $galleries = 0;
foreach ($bundle['records'] as $record) {
    if ($record['language'] !== 'en') continue;
    $matches = get_posts(['post_type' => ['cml_product','post','page'], 'numberposts' => 1, 'lang' => '', 'meta_key' => '_cml_import_key', 'meta_value' => $record['sourceId'] . ':en']);
    if (!$matches) { $failed[] = $record['sourceId'] . ' missing'; continue; }
    if (!empty($record['publishedAt'])) { $dates++; if (get_post_field('post_date', $matches[0]->ID) !== $record['publishedAt']) $failed[] = $record['sourceId'] . ' date'; }
    if (in_array($record['sourceId'], ['product:159','product:173'], true)) {
        $galleries++;
        $content = get_post_field('post_content', $matches[0]->ID);
        if (substr_count($content, 'source gallery ') !== 2 || !str_contains($content, '/wp-content/uploads/cml/')) $failed[] = $record['sourceId'] . ' gallery';
    }
}
echo wp_json_encode(['sourceDates' => $dates, 'sourceGalleries' => $galleries, 'failed' => $failed]) . PHP_EOL;
if ($dates !== 64 || $galleries !== 2 || $failed) throw new RuntimeException('Source record reconciliation incomplete.');
