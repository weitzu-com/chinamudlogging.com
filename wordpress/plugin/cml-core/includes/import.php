<?php
defined('ABSPATH') || exit;

/** Import an explicit local bundle; never fetch arbitrary remote media. */
function cml_import_bundle(string $file, bool $publish_translations = false, bool $overwrite_edits = false): array {
    if (!function_exists('pll_set_post_language')) throw new RuntimeException('Activate free Polylang before importing.');
    $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (($data['schemaVersion'] ?? null) !== 1) throw new RuntimeException('Unsupported bundle schema.');
    $root = dirname(realpath($file));
    $media = [];
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    foreach ($data['media'] as $item) {
        $source = realpath($root . '/assets/' . ltrim($item['path'], '/'));
        if (!$source || !str_starts_with($source, $root . '/assets/') || !hash_equals($item['sha256'], hash_file('sha256', $source))) throw new RuntimeException('Media integrity failure: ' . $item['path']);
        $existing = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'lang' => '', 'meta_key' => '_cml_asset_key', 'meta_value' => $item['sha256']]);
        if ($existing) {
            $id = $existing[0]->ID;
        } else {
            $uploads = wp_upload_dir();
            if ($uploads['error']) throw new RuntimeException($uploads['error']);
            $target_dir = $uploads['basedir'] . '/cml/' . substr($item['sha256'], 0, 16);
            wp_mkdir_p($target_dir);
            $target = $target_dir . '/' . sanitize_file_name(basename($source));
            $type = wp_check_filetype_and_ext($source, basename($source));
            if (!$type['type'] || !in_array($type['type'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'], true)) throw new RuntimeException('Unsupported asset type: ' . $item['path']);
            if (!copy($source, $target)) throw new RuntimeException('Cannot copy media.');
            $id = wp_insert_attachment(['post_name' => 'cml-asset-' . substr($item['sha256'], 0, 20), 'post_title' => sanitize_text_field(pathinfo($source, PATHINFO_FILENAME)), 'post_mime_type' => $type['type'], 'post_status' => 'inherit'], $target, 0, true);
            if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
            wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $target));
            update_post_meta($id, '_cml_asset_key', $item['sha256']);
        }
        $media[$item['path']] = ['id' => $id, 'url' => wp_get_attachment_url($id)];
    }
    $terms = []; $term_links = [];
    foreach ($data['categories'] as $category) {
        foreach ($category['translations'] as $lang => $translation) {
            $slug = $category['slug'] . ($lang === 'en' ? '' : '-' . $lang);
            $term = get_term_by('slug', $slug, 'cml_family');
            if (!$term) {
                $created = wp_insert_term($translation, 'cml_family', ['slug' => $slug]);
                if (is_wp_error($created)) throw new RuntimeException($created->get_error_message());
                $id = $created['term_id'];
            } else { $id = $term->term_id; wp_update_term($id, 'cml_family', ['name' => $translation]); }
            pll_set_term_language($id, $lang);
            $terms[$lang][$category['slug']] = $id;
            $term_links[$category['slug']][$lang] = $id;
        }
    }
    foreach ($term_links as $links) pll_save_term_translations($links);
    $ids = []; $pages = [];
    foreach ($data['records'] as $record) {
        $key = $record['sourceId'] . ':' . $record['language'];
        $matches = get_posts(['post_type' => ['cml_product', 'post', 'page'], 'post_status' => ['publish', 'draft', 'pending', 'private', 'trash'], 'numberposts' => 2, 'lang' => '', 'meta_key' => '_cml_import_key', 'meta_value' => $key]);
        if (count($matches) > 1) throw new RuntimeException('Duplicate import key: ' . $key);
        if ($matches && !$overwrite_edits) {
            $snapshot = get_post_meta($matches[0]->ID, '_cml_editor_snapshot', true);
            if (is_array($snapshot)) $snapshot += ['specifications' => [], 'published' => get_post_field('post_date', $matches[0]->ID), 'image' => get_post_thumbnail_id($matches[0]->ID), 'families' => cml_snapshot_families($matches[0]->ID)];
            $current_snapshot = cml_editor_snapshot($matches[0]->ID);
            if (is_array($snapshot)) { ksort($snapshot); ksort($current_snapshot); }
            if ($snapshot && $snapshot !== $current_snapshot) throw new RuntimeException('Editor changes detected for ' . $key . '. Export/reconcile changes before using --overwrite-edits.');
        }
        $content = $record['content'];
        foreach ($media as $old => $asset) $content = str_replace('"' . $old . '"', '"' . esc_url($asset['url']) . '"', $content);
        $status = $record['language'] === 'en' || ($publish_translations && $record['review'] === 'approved') ? 'publish' : 'draft';
        $post = [
            'post_type' => $record['type'], 'post_title' => $record['title'], 'post_name' => $record['slug'],
            'post_content' => $content, 'post_excerpt' => $record['description'], 'post_status' => $status,
            'comment_status' => 'closed', 'ping_status' => 'closed',
        ];
        if (!empty($record['publishedAt'])) $post['post_date'] = strlen($record['publishedAt']) > 10 ? substr(str_replace('T', ' ', $record['publishedAt']), 0, 19) : $record['publishedAt'] . ' 12:00:00';
        if ($matches) $post['ID'] = $matches[0]->ID;
        $id = wp_insert_post(wp_slash($post), true);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        update_post_meta($id, '_cml_import_key', $key);
        pll_set_post_language($id, $record['language']);
        if (get_post_field('post_name', $id) !== $record['slug']) {
            wp_update_post(['ID' => $id, 'post_status' => 'draft']);
            throw new RuntimeException('URL collision: ' . $record['slug'] . '. Record held as a tracked draft. Resolve before publishing.');
        }
        update_post_meta($id, '_cml_source_id', $record['sourceId']);
        update_post_meta($id, '_cml_source_url', $record['sourceUrl']);
        if (!empty($record['publishedAt'])) update_post_meta($id, '_cml_source_published_at', $record['publishedAt']);
        update_post_meta($id, '_cml_review', $record['review']);
        update_post_meta($id, '_cml_localization_mode', $record['localizationMode'] ?? 'full-source-record');
        update_post_meta($id, '_cml_description', $record['description']);
        update_post_meta($id, '_yoast_wpseo_metadesc', $record['description']);
        foreach (['sku', 'weight', 'signal', 'range', 'interface'] as $field) update_post_meta($id, '_cml_' . $field, $record['fields'][$field] ?? '');
        update_post_meta($id, '_cml_specifications', wp_slash(cml_clean_specifications($record['fields']['specifications'] ?? [])));
        if (!empty($record['image']) && isset($media[$record['image']])) set_post_thumbnail($id, $media[$record['image']]['id']);
        if (!empty($record['category'])) wp_set_object_terms($id, [(int)$terms[$record['language']][$record['category']]], 'cml_family');
        update_post_meta($id, '_cml_editor_snapshot', wp_slash(cml_editor_snapshot($id)));
        if (!empty($record['pageKey'])) $pages[$record['language']][$record['pageKey']] = $id;
        $ids[$record['sourceId']][$record['language']] = $id;
    }
    foreach ($ids as $links) pll_save_post_translations($links);
    update_option('cml_pages', $pages, false);
    update_option('show_on_front', 'page');
    update_option('page_on_front', $pages['en']['home']);
    update_option('page_for_posts', $pages['en']['insights']);
    update_option('wp_page_for_privacy_policy', $pages['en']['privacy']);
    update_option('permalink_structure', '/%postname%/');
    update_option('cml_redirects', $data['redirects'], false);
    $asset_redirects = array_map(fn($asset) => $asset['url'], $media);
    foreach ($media as $old_path => $asset) {
        if (str_starts_with($old_path, '/images/source/')) $asset_redirects['/wp-content/uploads/' . substr($old_path, strlen('/images/source/'))] = $asset['url'];
    }
    update_option('cml_asset_redirects', $asset_redirects, false);
    if (isset($media[$data['heroImage']])) update_option('cml_hero_image', $media[$data['heroImage']]['url']);
    update_option('cml_import_digest', hash_file('sha256', $file));
    flush_rewrite_rules();
    return ['records' => count($data['records']), 'media' => count($media), 'translations_published' => $publish_translations];
}

function cml_editor_snapshot(int $id): array {
    $post = get_post($id);
    $snapshot = ['title' => $post->post_title, 'content' => $post->post_content, 'excerpt' => $post->post_excerpt, 'published' => $post->post_date];
    foreach (['sku', 'weight', 'signal', 'range', 'interface'] as $field) $snapshot[$field] = get_post_meta($id, '_cml_' . $field, true);
    $snapshot['specifications'] = get_post_meta($id, '_cml_specifications', true) ?: [];
    $snapshot['image'] = get_post_thumbnail_id($id);
    $snapshot['families'] = cml_snapshot_families($id);
    return $snapshot;
}
function cml_snapshot_families(int $id): array {
    $ids = wp_get_object_terms($id, 'cml_family', ['fields' => 'ids', 'lang' => '']);
    if (is_wp_error($ids)) return [];
    $ids = array_map('intval', $ids); sort($ids); return $ids;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('cml import', function ($args, $assoc) {
        if (empty($args[0]) || !is_file($args[0])) WP_CLI::error('Usage: wp cml import /absolute/path/bundle.json [--publish-reviewed]');
        try { WP_CLI::success(wp_json_encode(cml_import_bundle($args[0], isset($assoc['publish-reviewed']), isset($assoc['overwrite-edits'])))); }
        catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
    });
}
