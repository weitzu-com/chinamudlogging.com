<?php
/** Preserve the existing anonymous measurement service without collecting form data. */
defined('ABSPATH') || exit;
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('cml-funnel', plugins_url('funnel.js', CML_CORE_DIR . '/cml-core.php'), [], filemtime(CML_CORE_DIR . '/funnel.js'), true);
});
add_action('parse_request', function () {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = wp_parse_url($uri, PHP_URL_PATH);
    if (!str_starts_with($path ?: '/', '/api/funnel/')) return;
    $route = substr($path, strlen('/api/funnel/'));
    $allowed = ['health','session','event','intent','admin/report','admin/receipt','admin/qualify','admin/test-session','admin/zoho/status','admin/zoho/sync','admin/zoho/evidence'];
    if (!in_array($route, $allowed, true) || !in_array($_SERVER['REQUEST_METHOD'], ['GET','POST','OPTIONS'], true)) { status_header(404); exit; }
    $body = file_get_contents('php://input', false, null, 0, 4097);
    if (strlen($body) > 4096 || strlen($uri) > 3000) { status_header(413); exit; }
    $headers = ['Content-Type' => 'application/json', 'User-Agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '')];
    if (isset($_SERVER['HTTP_ORIGIN'])) $headers['Origin'] = $_SERVER['HTTP_ORIGIN'];
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($authorization, 'Bearer ')) $headers['Authorization'] = $authorization;
    $response = wp_remote_request('https://chinamudlogging-funnel.kenny-bd6.workers.dev' . $uri, [
        'method' => $_SERVER['REQUEST_METHOD'], 'headers' => $headers, 'body' => $body,
        'timeout' => 12, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 2000000,
    ]);
    header('Content-Type: application/json'); header('Cache-Control: no-store, private'); header('X-Robots-Tag: noindex, nofollow');
    if (is_wp_error($response)) { status_header(503); exit('{"error":"Service unavailable"}'); }
    status_header(wp_remote_retrieve_response_code($response));
    echo wp_remote_retrieve_body($response);
    exit;
});
