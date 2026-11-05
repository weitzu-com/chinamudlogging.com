<?php
/** wp eval-file mail-acceptance.php failure|LANG; only synthetic test records. */
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('CLI only.');
global $wpdb;
$mode = $args[0] ?? '';
if ($mode === 'failure') {
    $filter = fn() => false;
    add_filter('pre_wp_mail', $filter);
    $request = new WP_REST_Request('POST', '/cml/v1/inquiries');
    $request->set_header('content-type', 'application/json');
    $request->set_body(wp_json_encode(['nonce' => wp_create_nonce('cml_inquiry'), 'request_id' => wp_generate_uuid4(),
        'language' => 'en', 'name' => 'WEBSITE ACCEPTANCE TEST', 'email' => 'test@example.invalid',
        'company' => 'TEST ONLY', 'country' => 'TEST ONLY', 'product' => 'SIMULATED MAIL FAILURE',
        'message' => 'TEST ONLY: forced transport rejection; record must survive.', 'source' => '/contact-us/', 'consent' => true, 'website' => '']));
    $result = cml_receive_inquiry($request);
    remove_filter('pre_wp_mail', $filter);
    if (is_wp_error($result)) throw new RuntimeException($result->get_error_code());
    $receipt = $result->get_data()['receipt'];
    $row = $wpdb->get_row($wpdb->prepare("SELECT id,mail_status FROM {$wpdb->prefix}cml_inquiries WHERE receipt=%s", $receipt));
    if ($row->mail_status !== 'failed') throw new RuntimeException('Failure status was not preserved.');
    echo wp_json_encode(['receipt' => $receipt, 'saved' => true, 'status' => $row->mail_status]) . PHP_EOL;
    // Acknowledge this known synthetic failure without deleting its evidence.
    $wpdb->update($wpdb->prefix . 'cml_inquiries', ['mail_status' => 'test_failure_verified'], ['id' => $row->id]);
} elseif (in_array($mode, ['en','zh','es','ru','ar','fr','de'], true)) {
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id,receipt,payload FROM {$wpdb->prefix}cml_inquiries WHERE language=%s ORDER BY id", $mode));
    foreach ($rows as $row) {
        $payload = json_decode($row->payload, true);
        if (($payload['company'] ?? '') !== 'TEST ONLY' || ($payload['product'] ?? '') !== 'CML acceptance ' . $mode) continue;
        echo wp_json_encode(['language' => $mode, 'receipt' => $row->receipt, 'status' => cml_dispatch_inquiry((int)$row->id)]) . PHP_EOL;
        return;
    }
    throw new RuntimeException('No matching synthetic test record.');
} else throw new RuntimeException('Use failure or one language code.');
