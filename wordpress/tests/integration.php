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
cml_import_bundle('/bundle/bundle.json');
cml_assert_test($before === [$count_imported(), $count_media()], 'Repeated import preserves record and media counts');
$bundle = json_decode(file_get_contents('/bundle/bundle.json'), true);
foreach ($bundle['records'] as $record) {
    $matches = get_posts(['post_type' => ['page','post','cml_product'], 'post_status' => ['publish','draft'], 'numberposts' => 1, 'lang' => '', 'meta_key' => '_cml_import_key', 'meta_value' => $record['sourceId'] . ':' . $record['language']]);
    cml_assert_test($matches && $matches[0]->post_name === $record['slug'], 'Stable slug ' . $record['sourceId'] . ':' . $record['language']);
}
$products = get_posts(['post_type' => 'cml_product', 'posts_per_page' => -1, 'lang' => 'en', 'post_status' => 'publish']);
cml_assert_test(count($products) === 63, '63 English products published');
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
// Publish pending content ONLY in this disposable local environment for visual QA.
$drafts = get_posts(['post_type' => ['page','cml_product'], 'post_status' => 'draft', 'posts_per_page' => -1, 'lang' => '', 'meta_key' => '_cml_import_key']);
foreach ($drafts as $post) wp_update_post(['ID' => $post->ID, 'post_status' => 'publish']);
flush_rewrite_rules();
$report = ['environment' => 'local WordPress Playground PHP 8.3 / SQLite; mail mocked', 'wordpressVersion' => get_bloginfo('version'), 'polylangVersion' => POLYLANG_VERSION, 'tests' => $results, 'passed' => count(array_filter($results, fn($r) => $r['passed'])), 'total' => count($results)];
file_put_contents('/cml-results/integration-results.json', wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
if ($report['passed'] !== $report['total']) throw new RuntimeException('Integration tests failed; inspect integration-results.json');
echo 'CML integration tests: ' . $report['passed'] . '/' . $report['total'];
