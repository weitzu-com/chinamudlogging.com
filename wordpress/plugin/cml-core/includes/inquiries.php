<?php
defined('ABSPATH') || exit;

function cml_install_inquiries(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$wpdb->prefix}cml_inquiries (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        request_key char(64) NOT NULL,
        payload_hash char(64) NOT NULL,
        receipt char(36) NOT NULL,
        created_at datetime NOT NULL,
        language varchar(8) NOT NULL,
        payload longtext NOT NULL,
        mail_status varchar(32) NOT NULL DEFAULT 'queued',
        PRIMARY KEY  (id),
        UNIQUE KEY request_key (request_key),
        UNIQUE KEY receipt (receipt)
    ) $charset;");
    dbDelta("CREATE TABLE {$wpdb->prefix}cml_limits (
        bucket char(64) NOT NULL,
        attempts int NOT NULL DEFAULT 0,
        expires_at bigint(20) NOT NULL,
        PRIMARY KEY  (bucket)
    ) $charset;");
}

add_action('rest_api_init', function () {
    register_rest_route('cml/v1', '/form-token', [
        'methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => function () {
            $response = new WP_REST_Response(['nonce' => wp_create_nonce('cml_inquiry')]);
            $response->header('Cache-Control', 'no-store, private, max-age=0');
            return $response;
        },
    ]);
    register_rest_route('cml/v1', '/inquiries', [
        'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'cml_receive_inquiry',
    ]);
});

function cml_receive_inquiry(WP_REST_Request $request) {
    global $wpdb;
    $error = fn($code, $status) => new WP_Error($code, $code, ['status' => $status]);
    if (strlen($request->get_body()) > 24000) return $error('request_too_large', 413);
    $data = $request->get_json_params();
    if (!is_array($data)) return $error('invalid_request', 400);
    if (!empty($data['website'])) return $error('invalid_request', 400);
    if (!is_string($data['nonce'] ?? null) || !wp_verify_nonce($data['nonce'], 'cml_inquiry')) return $error('expired_form', 403);
    $origin = $request->get_header('origin');
    if ($origin && (wp_parse_url($origin, PHP_URL_HOST) !== wp_parse_url(home_url(), PHP_URL_HOST))) return $error('invalid_origin', 403);
    if (!is_string($data['request_id'] ?? null) || !preg_match('/^[a-f0-9-]{36}$/i', $data['request_id'])) return $error('invalid_request_id', 400);
    $reference = $data['tracking_reference'] ?? '';
    if (!is_string($reference) || ($reference !== '' && !preg_match('/^CML-[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $reference))) return $error('invalid_reference', 400);
    $language = $data['language'] ?? 'en';
    if (!is_string($language) || !in_array($language, ['en', 'zh', 'es', 'ru', 'ar', 'fr', 'de'], true)) return $error('invalid_language', 400);
    if (($data['consent'] ?? false) !== true) return $error('consent_required', 400);
    $payload = [];
    foreach (['name' => 160, 'email' => 254, 'company' => 200, 'country' => 120, 'product' => 300, 'message' => 8000, 'source' => 500] as $field => $max) {
        if (!is_string($data[$field] ?? '')) return $error('invalid_' . $field, 400);
        $value = trim($data[$field] ?? '');
        if (mb_strlen($value, 'UTF-8') > $max) return $error('invalid_' . $field, 400);
        $payload[$field] = $field === 'message' ? sanitize_textarea_field($value) : sanitize_text_field($value);
    }
    if (!$payload['name'] || !$payload['message'] || !is_email($payload['email'])) return $error('required_fields', 400);
    // Store only a source path; discard queries, fragments, and any external origin.
    $source = wp_parse_url($payload['source'], PHP_URL_PATH) ?: '/';
    $payload['source'] = '/' . ltrim($source, '/');
    $encoded = wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
    $hash = hash('sha256', $encoded . $language . $reference);
    $key = hash_hmac('sha256', $data['request_id'], wp_salt('nonce'));
    $table = $wpdb->prefix . 'cml_inquiries';
    $old = $wpdb->get_row($wpdb->prepare("SELECT receipt, payload_hash FROM $table WHERE request_key = %s", $key));
    if ($old) {
        if (!hash_equals($old->payload_hash, $hash)) return $error('idempotency_conflict', 409);
        return new WP_REST_Response(['receipt' => $old->receipt, 'saved' => true], 200);
    }
    // The direct peer address is trusted; user-controlled forwarded headers are ignored.
    $bucket = hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . gmdate('YmdH'), wp_salt('auth'));
    $limits = $wpdb->prefix . 'cml_limits';
    $wpdb->query($wpdb->prepare("DELETE FROM $limits WHERE expires_at < %d", time()));
    $changed = $wpdb->query($wpdb->prepare("INSERT INTO $limits (bucket, attempts, expires_at) VALUES (%s, 1, %d) ON DUPLICATE KEY UPDATE attempts = attempts + 1", $bucket, time() + HOUR_IN_SECONDS));
    if ($changed === false) return $error('temporarily_unavailable', 503);
    $attempts = (int)$wpdb->get_var($wpdb->prepare("SELECT attempts FROM $limits WHERE bucket = %s", $bucket));
    if ($attempts > 5) return $error('rate_limited', 429);
    $receipt = $reference !== '' ? substr($reference, 4) : wp_generate_uuid4();
    $ok = $wpdb->insert($table, [
        'request_key' => $key, 'payload_hash' => $hash, 'receipt' => $receipt,
        'created_at' => current_time('mysql', true), 'language' => $language,
        'payload' => $encoded, 'mail_status' => 'queued',
    ]);
    if (!$ok) {
        $old = $wpdb->get_row($wpdb->prepare("SELECT receipt, payload_hash FROM $table WHERE request_key = %s", $key));
        if ($old && hash_equals($old->payload_hash, $hash)) return new WP_REST_Response(['receipt' => $old->receipt, 'saved' => true], 200);
        return $error('save_failed', 503);
    }
    $id = (int)$wpdb->insert_id;
    cml_dispatch_inquiry($id);
    // Never expose delivery state, customer content, or personal data through a public API.
    return new WP_REST_Response(['receipt' => $receipt, 'saved' => true], 201);
}

/** Notify one saved record; re-sending is an explicit administrator operation. */
function cml_dispatch_inquiry(int $id): string {
    global $wpdb;
    $table = $wpdb->prefix . 'cml_inquiries';
    $row = $wpdb->get_row($wpdb->prepare("SELECT receipt, language, payload FROM $table WHERE id=%d", $id));
    if (!$row) throw new RuntimeException('Saved inquiry required.');
    $receipt = $row->receipt; $language = $row->language;
    $payload = json_decode($row->payload, true);
    $status = 'notification_disabled';
    if (get_option('cml_mail_enabled', false)) {
        $recipient = get_option('cml_recipient', 'sales@chinamudlogging.com');
        $body = "Receipt: $receipt\nLanguage: $language\n\n";
        foreach ($payload as $field => $value) $body .= ucfirst($field) . ': ' . $value . "\n";
        $previous_message_id = null;
        $trace = function ($mailer) use ($receipt, &$previous_message_id) {
            $previous_message_id = $mailer->MessageID;
            $mailer->MessageID = '<cml-' . $receipt . '@chinamudlogging.com>';
        };
        add_action('phpmailer_init', $trace, 200);
        try {
            $accepted = is_email($recipient) && wp_mail($recipient, 'Website inquiry [CML-' . $receipt . ']', $body, ['Reply-To: ' . $payload['email']]);
        } catch (Throwable $error) {
            $accepted = false;
        } finally {
            remove_action('phpmailer_init', $trace, 200);
            if ($previous_message_id !== null && isset($GLOBALS['phpmailer'])) $GLOBALS['phpmailer']->MessageID = $previous_message_id;
        }
        $status = $accepted ? 'transport_accepted' : 'failed';
    }
    $wpdb->update($table, ['mail_status' => $status], ['id' => $id]);
    return $status;
}

add_action('admin_menu', function () {
    add_menu_page('Inquiries', 'Inquiries', 'manage_options', 'cml-inquiries', 'cml_inquiries_admin', 'dashicons-email-alt');
});
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cml_inquiries WHERE mail_status IN ('failed','notification_disabled','queued')");
    if ($count) echo '<div class="notice notice-warning"><p>' . esc_html($count) . ' saved inquiries need notification review. <a href="' . esc_url(admin_url('admin.php?page=cml-inquiries')) . '">Open inquiries</a></p></div>';
});
function cml_inquiries_admin(): void {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    echo '<div class="wrap"><h1>Website inquiries</h1><p>transport_accepted means the mail transport accepted the message. It does not prove mailbox delivery.</p>';
    $page = max(1, absint($_GET['paged'] ?? 1));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cml_inquiries ORDER BY id DESC LIMIT 30 OFFSET %d", ($page - 1) * 30));
    echo '<table class="widefat striped"><thead><tr><th>UTC / receipt</th><th>Language / notification</th><th>Requirement</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . esc_html($row->created_at) . '<br>' . esc_html($row->receipt) . '</td><td>' . esc_html($row->language) . '<br>' . esc_html($row->mail_status) . '</td><td>';
        foreach ((json_decode($row->payload, true) ?: []) as $key => $value) echo '<p><strong>' . esc_html($key) . ':</strong> ' . nl2br(esc_html($value)) . '</p>';
        echo '</td></tr>';
    }
    echo '</tbody></table>';
    if ($page > 1) echo '<a href="' . esc_url(add_query_arg('paged', $page - 1)) . '">Previous</a> ';
    if (count($rows) === 30) echo '<a href="' . esc_url(add_query_arg('paged', $page + 1)) . '">Next</a>';
    echo '</div>';
}
