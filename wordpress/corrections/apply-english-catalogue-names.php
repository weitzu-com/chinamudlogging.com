<?php
/** WP-CLI eval-file only; dry-run unless the second argument is apply or rollback. */
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('WP-CLI required.');
$plan = json_decode(file_get_contents($args[0] ?? ''), true, 512, JSON_THROW_ON_ERROR);
$mode = $args[1] ?? 'dry-run';
if (!in_array($mode, ['dry-run', 'apply', 'rollback'], true)) throw new RuntimeException('Unknown mode.');
$invariant = function ($id) {
    $post = get_post($id);
    return hash('sha256', wp_json_encode([
        $post->post_content, $post->post_name, $post->post_date, $post->post_date_gmt, $post->post_status,
        pll_get_post_language($id), get_post_thumbnail_id($id),
        wp_get_object_terms($id, 'cml_family', ['fields' => 'ids']),
        get_post_meta($id, '_cml_specifications', true), get_post_meta($id, '_cml_sku', true), get_post_meta($id, '_cml_weight', true),
    ]));
};
$pending = []; $unchanged = 0;
foreach ($plan['records'] as $row) {
    $post = get_post($row['id']);
    if (!$post || $post->post_type !== 'cml_product' || $post->post_status !== 'publish' || $post->post_name !== $row['slug'] || pll_get_post_language($post->ID) !== 'en') throw new RuntimeException('Scope mismatch: ' . $row['id']);
    foreach (['title', 'excerpt'] as $field) {
        preg_match_all('/\d+(?:\.\d+)?/', $row['before'][$field], $before_numbers);
        preg_match_all('/\d+(?:\.\d+)?/', $row['after'][$field], $after_numbers);
        if ($before_numbers !== $after_numbers) throw new RuntimeException('Numeric source changed.');
    }
    $current = ['title' => $post->post_title, 'excerpt' => $post->post_excerpt];
    $from = $row[$mode === 'rollback' ? 'after' : 'before'];
    $to = $row[$mode === 'rollback' ? 'before' : 'after'];
    if ($current === $to) { $unchanged++; continue; }
    if ($current !== $from) throw new RuntimeException('Concurrent edit; no writes performed: ' . $post->ID);
    $pending[] = ['row' => $row, 'to' => $to, 'invariant' => $invariant($post->ID)];
}
$verified = 0;
if ($mode !== 'dry-run') foreach ($pending as $item) {
    $row = $item['row'];
    add_post_meta($row['id'], '_cml_ego_name_before_20261105', wp_slash(wp_json_encode($row['before'], JSON_UNESCAPED_UNICODE)), true);
    $id = wp_update_post(wp_slash(['ID' => $row['id'], 'post_title' => $item['to']['title'], 'post_excerpt' => $item['to']['excerpt']]), true);
    if (is_wp_error($id)) throw new RuntimeException('Update failed: ' . $row['id']);
    if ($invariant($id) !== $item['invariant']) throw new RuntimeException('Protected field changed: ' . $id);
    $post = get_post($id);
    if ($post->post_title !== $item['to']['title'] || $post->post_excerpt !== $item['to']['excerpt']) throw new RuntimeException('Copy readback failed: ' . $id);
    $verified++;
}
echo wp_json_encode(['mode' => $mode, 'planned' => count($plan['records']), 'pending' => count($pending), 'already_at_target' => $unchanged, 'protected_field_checks_passed' => $verified], JSON_PRETTY_PRINT);
