<?php
// This suite modifies only the explicitly isolated Playground database.
if (!defined('CML_INTEGRATION_TEST') || !CML_INTEGRATION_TEST) throw new RuntimeException('Local test environment required.');
$results = [];
function cml_assert_test($condition, string $name): void {
    global $results;
    $results[] = ['name' => $name, 'passed' => (bool)$condition];
    if (!$condition) error_log('CML TEST FAILED: ' . $name);
}
global $wpdb;
$count_imported = fn() => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_cml_import_key'");
$count_media = fn() => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_cml_asset_key'");
$before = [$count_imported(), $count_media()];
add_filter('intermediate_image_sizes_advanced', '__return_empty_array');
$legacy_id = get_posts(['post_type' => 'cml_product', 'numberposts' => 1, 'lang' => '', 'meta_key' => '_cml_import_key', 'meta_value' => 'product:305:en'])[0]->ID;
$legacy_snapshot = get_post_meta($legacy_id, '_cml_editor_snapshot', true);
unset($legacy_snapshot['published'], $legacy_snapshot['image'], $legacy_snapshot['families']);
update_post_meta($legacy_id, '_cml_editor_snapshot', wp_slash(array_reverse($legacy_snapshot, true)));
cml_import_bundle('/bundle/bundle.json');
cml_assert_test(array_key_exists('published', get_post_meta($legacy_id, '_cml_editor_snapshot', true)), 'Unchanged legacy editor snapshots upgrade without requiring an overwrite');
cml_assert_test($before === [$count_imported(), $count_media()], 'Repeated import preserves record and media counts');
$bundle = json_decode(file_get_contents('/bundle/bundle.json'), true);
foreach ($bundle['records'] as $record) {
    $matches = get_posts(['post_type' => ['page','post','cml_product'], 'post_status' => ['publish','draft'], 'numberposts' => 1, 'lang' => '', 'meta_key' => '_cml_import_key', 'meta_value' => $record['sourceId'] . ':' . $record['language']]);
    cml_assert_test($matches && $matches[0]->post_name === $record['slug'], 'Stable slug ' . $record['sourceId'] . ':' . $record['language']);
}
foreach ($bundle['records'] as $record) {
    if (($record['localizationMode'] ?? '') !== 'full-source-translation-reviewed') continue;
    $matches = get_posts(['post_type'=>'cml_product','post_status'=>['publish','draft'],'numberposts'=>1,'lang'=>'','meta_key'=>'_cml_import_key','meta_value'=>$record['sourceId'].':'.$record['language']]);
    $expected_content=$record['content'];
    foreach ($bundle['media'] as $asset) {
        $attachments=get_posts(['post_type'=>'attachment','post_status'=>'inherit','numberposts'=>1,'lang'=>'','meta_key'=>'_cml_asset_key','meta_value'=>$asset['sha256']]);
        if ($attachments) $expected_content=str_replace('"'.$asset['path'].'"','"'.esc_url(wp_get_attachment_url($attachments[0]->ID)).'"',$expected_content);
    }
    cml_assert_test($matches && $matches[0]->post_content === $expected_content, 'Full translation survives real WordPress sanitization: '.$record['sourceId'].':'.$record['language']);
}
$products = get_posts(['post_type' => 'cml_product', 'posts_per_page' => -1, 'lang' => 'en', 'post_status' => 'publish']);
$dates_ok = true; $dated_count = 0;
foreach ($bundle['records'] as $record) if ($record['language'] === 'en' && !empty($record['publishedAt'])) {
    $matches = get_posts(['post_type' => ['cml_product','post'], 'numberposts' => 1, 'lang' => '', 'meta_key' => '_cml_import_key', 'meta_value' => $record['sourceId'] . ':en']);
    if (!$matches) continue;
    $dated_count++; $dates_ok = $dates_ok && get_post_field('post_date', $matches[0]->ID) === $record['publishedAt'];
}
cml_assert_test($dated_count === 64 && $dates_ok, 'All 56 known product and 8 article source publication timestamps retained');
$gallery_id = get_posts(['post_type' => 'cml_product', 'numberposts' => 1, 'lang' => '', 'meta_key' => '_cml_import_key', 'meta_value' => 'product:159:en'])[0]->ID;
cml_assert_test(substr_count(get_post_field('post_content', $gallery_id), 'source gallery ') === 2 && str_contains(get_post_field('post_content', $gallery_id), '/wp-content/uploads/cml/'), 'Original source gallery images remain associated with their product and deduplicated media');
$dated_id = get_posts(['post_type' => 'cml_product', 'numberposts' => 1, 'lang' => '', 'meta_key' => '_cml_import_key', 'meta_value' => 'product:245:en'])[0]->ID;
$original_date = get_post_field('post_date', $dated_id);
wp_update_post(['ID' => $dated_id, 'post_date' => '2020-01-01 00:00:00']);
$date_blocked = false;
try { cml_import_bundle('/bundle/bundle.json'); } catch (RuntimeException $error) { $date_blocked = str_contains($error->getMessage(), 'Editor changes detected'); }
cml_assert_test($date_blocked, 'Reimport protects manually changed publication dates');
cml_import_bundle('/bundle/bundle.json', false, true);
cml_assert_test(get_post_field('post_date', $dated_id) === $original_date, 'Explicit reconciled import restores the recorded source publication date');
cml_assert_test(count($products) === 63, '63 English products published');
$native_id = $products[0]->ID;
$native_image = get_post_thumbnail_id($native_id); $native_families = cml_snapshot_families($native_id);
delete_post_thumbnail($native_id); wp_set_object_terms($native_id, [], 'cml_family');
$native_blocked = false;
try { cml_import_bundle('/bundle/bundle.json'); } catch (RuntimeException $error) { $native_blocked = str_contains($error->getMessage(), 'Editor changes detected'); }
cml_assert_test($native_blocked, 'Native featured-image and category edits are protected from silent reimport');
cml_import_bundle('/bundle/bundle.json', false, true);
cml_assert_test(get_post_thumbnail_id($native_id) === $native_image && cml_snapshot_families($native_id) === $native_families, 'Explicit reconciled import restores the recorded media and category associations');
cml_assert_test(cml_clean_specifications([['label' => 'Accuracy', 'raw' => '<0.05 %', 'value' => '<0.05', 'unit' => '%']])[0]['raw'] === '<0.05 %', 'Structured specifications preserve comparison symbols and source precision');
$specification_rows = get_post_meta($products[0]->ID, '_cml_specifications', true);
cml_assert_test(is_array($specification_rows), 'Product specifications stored as structured rows independently of theme');
$edited_id = $products[0]->ID;
$edited_content = $products[0]->post_content . '\nEditor change must survive';
wp_update_post(wp_slash(['ID' => $edited_id, 'post_content' => $edited_content]));
$edit_blocked = false;
try { cml_import_bundle('/bundle/bundle.json'); }
catch (RuntimeException $error) { $edit_blocked = str_contains($error->getMessage(), 'Editor changes detected'); }
cml_assert_test($edit_blocked && get_post_field('post_content', $edited_id) === $edited_content, 'Reimport cannot silently overwrite editor changes');
cml_import_bundle('/bundle/bundle.json', false, true);
cml_assert_test(!str_contains(get_post_field('post_content', $edited_id), 'Editor change must survive'), 'Explicit reconciled overwrite restores the source record');
cml_assert_test(count(get_posts(['post_type' => 'post', 'posts_per_page' => -1, 'lang' => 'en', 'meta_key' => '_cml_import_key'])) === 8, '8 English source articles imported');
cml_assert_test(count(pll_languages_list()) === 7, 'Seven languages configured');
foreach (['zh','es','ru','ar','fr','de'] as $lang) {
    $published = get_posts(['post_type' => ['page','cml_product'], 'post_status' => 'publish', 'posts_per_page' => 1, 'lang' => $lang]);
    cml_assert_test(!$published, $lang . ' content stays draft without explicit publish-reviewed');
    cml_assert_test(count(cml_strings($lang)) === count(cml_strings('en')), $lang . ' has every UI string');
}
cml_assert_test(PLL()->model->languages->get('ar')->is_rtl, 'Arabic language is RTL');
wp_set_current_user(0);
cml_assert_test(!current_user_can('manage_options'), 'Guest cannot access private inquiry administration');
$payload = [
    'nonce' => wp_create_nonce('cml_inquiry'), 'request_id' => wp_generate_uuid4(), 'language' => 'en',
    'name' => 'LOCAL ACCEPTANCE TEST', 'email' => 'test@example.invalid', 'company' => 'Test only',
    'country' => 'Test', 'product' => 'Test sensor', 'message' => 'Local test. Do not contact.',
    'source' => '/contact-us/?email=must-not-store@example.invalid', 'consent' => true, 'website' => '',
];
function cml_test_submit(array $data) {
    $request = new WP_REST_Request('POST', '/cml/v1/inquiries');
    $request->set_header('content-type', 'application/json');
    $request->set_body(wp_json_encode($data));
    return cml_receive_inquiry($request);
}
$mail_attempts = 0; $mail_accepts = true;
add_filter('pre_wp_mail', function () use (&$mail_attempts, &$mail_accepts) { $mail_attempts++; return $mail_accepts; });
update_option('cml_mail_enabled', true);
foreach (['en','zh','es','ru','ar','fr','de'] as $index => $lang) {
    $_SERVER['REMOTE_ADDR'] = '192.0.2.' . ($index+1);
    $data = array_merge($payload, ['language' => $lang, 'request_id' => wp_generate_uuid4()]);
    $reply = cml_test_submit($data);
    cml_assert_test($reply instanceof WP_REST_Response && $reply->get_status() === 201, $lang . ' inquiry stored with receipt');
    $again = cml_test_submit($data);
    cml_assert_test($again instanceof WP_REST_Response && $again->get_status() === 200 && $reply->get_data()['receipt'] === $again->get_data()['receipt'], $lang . ' duplicate returns same receipt');
}
cml_assert_test($mail_attempts === 7, 'Duplicate requests never trigger duplicate notifications');
$conflict = cml_test_submit(array_merge($data, ['message' => 'Changed body']));
cml_assert_test(is_wp_error($conflict) && $conflict->get_error_code() === 'idempotency_conflict', 'Request ID cannot be reused for different content');
$row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}cml_inquiries ORDER BY id DESC LIMIT 1");
cml_assert_test(json_decode($row->payload, true)['source'] === '/contact-us/', 'Source query strings are removed');
cml_assert_test($row->mail_status === 'transport_accepted', 'Accepted mail transport is recorded without claiming delivery');
$mail_accepts = false; $_SERVER['REMOTE_ADDR'] = '192.0.2.100';
$failed = cml_test_submit(array_merge($payload, ['request_id' => wp_generate_uuid4()]));
$row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}cml_inquiries ORDER BY id DESC LIMIT 1");
cml_assert_test($failed instanceof WP_REST_Response && $failed->get_status() === 201 && $row->mail_status === 'failed', 'Mail failure preserves saved inquiry and marks failure');
foreach ([['nonce' => 'invalid'], ['email' => 'invalid'], ['website' => 'spam.example'], ['language' => 'xx'], ['name' => ['bad']], ['consent' => false]] as $bad) {
    cml_assert_test(is_wp_error(cml_test_submit(array_merge($payload, $bad, ['request_id' => wp_generate_uuid4()]))), 'Invalid input rejected: ' . array_key_first($bad));
}
$_SERVER['REMOTE_ADDR'] = '192.0.2.200';
for ($i = 0; $i < 6; $i++) $limited = cml_test_submit(array_merge($payload, ['request_id' => wp_generate_uuid4()]));
cml_assert_test(is_wp_error($limited) && $limited->get_error_code() === 'rate_limited', 'Rate limit stops sixth unique request');
update_option('cml_mail_enabled', false);
$_SERVER['REMOTE_ADDR'] = '192.0.2.220';
$reference = 'CML-' . wp_generate_uuid4();
$tracked_data = array_merge($payload, ['request_id' => wp_generate_uuid4(), 'tracking_reference' => $reference]);
$tracked = cml_test_submit($tracked_data);
cml_assert_test($tracked instanceof WP_REST_Response && 'CML-' . $tracked->get_data()['receipt'] === $reference, 'Anonymous intent reference can be correlated with the saved inquiry without customer data');
$tracked_again = cml_test_submit($tracked_data);
cml_assert_test($tracked_again instanceof WP_REST_Response && $tracked_again->get_status() === 200, 'Tracked inquiry retry retains its original receipt');
$changed_reference = cml_test_submit(array_merge($tracked_data, ['tracking_reference' => 'CML-' . wp_generate_uuid4()]));
cml_assert_test(is_wp_error($changed_reference) && $changed_reference->get_error_code() === 'idempotency_conflict', 'A retry cannot silently change its anonymous reference');
cml_assert_test(is_wp_error(cml_test_submit(array_merge($payload, ['request_id' => wp_generate_uuid4(), 'tracking_reference' => 'invalid']))), 'Invalid measurement references are rejected');
// Publish pending content ONLY in this disposable local environment for visual QA.
$drafts = get_posts(['post_type' => ['page','cml_product'], 'post_status' => 'draft', 'posts_per_page' => -1, 'lang' => '', 'meta_key' => '_cml_import_key']);
foreach ($drafts as $post) wp_update_post(['ID' => $post->ID, 'post_status' => 'publish']);
flush_rewrite_rules();
$report = ['environment' => 'local WordPress Playground PHP 8.3 / SQLite; mail mocked', 'wordpressVersion' => get_bloginfo('version'), 'polylangVersion' => POLYLANG_VERSION, 'tests' => $results, 'passed' => count(array_filter($results, fn($r) => $r['passed'])), 'total' => count($results)];
file_put_contents('/cml-results/integration-results.json', wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if ($report['passed'] !== $report['total']) throw new RuntimeException('Integration tests failed; inspect integration-results.json');
echo 'CML integration tests: ' . $report['passed'] . '/' . $report['total'];
