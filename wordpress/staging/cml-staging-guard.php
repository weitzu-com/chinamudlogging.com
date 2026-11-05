<?php
/** Protect preview after release; production stays gated until acceptance. */
defined('ABSPATH') || exit;
if (PHP_SAPI === 'cli' || (defined('WP_CLI') && WP_CLI)) return;
$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
$released = (bool)get_option('cml_production_released', false);
if ($released && in_array($host, ['chinamudlogging.com', 'www.chinamudlogging.com'], true)) return;
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, private', true);
if ($released && $host === 'preview.chinamudlogging.com') {
    add_filter('pre_option_home', fn() => 'https://preview.chinamudlogging.com');
    add_filter('pre_option_siteurl', fn() => 'https://preview.chinamudlogging.com');
    add_filter('pre_option_blog_public', fn() => '0');
}
$path = dirname(rtrim(ABSPATH, '/'), 3) . '/.cml-staging/auth.json';
$auth = is_file($path) ? json_decode(file_get_contents($path), true) : [];
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$password = $_SERVER['PHP_AUTH_PW'] ?? '';
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!$user && str_starts_with($authorization, 'Basic ')) {
    $decoded = base64_decode(substr($authorization, 6), true);
    if ($decoded !== false && str_contains($decoded, ':')) [$user, $password] = explode(':', $decoded, 2);
}
if (!$auth || !hash_equals($auth['user'] ?? '', $user) || !password_verify($password, $auth['hash'] ?? '')) {
    header('WWW-Authenticate: Basic realm="ChinaMudLogging staging"');
    http_response_code(401);
    header('Cache-Control: no-store, private');
    exit('Protected staging site.');
}
// This is the staging gate, not a WordPress Application Password. Let normal
// WordPress cookies/nonces authenticate the CMS and keep guest REST forms usable.
unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
