<?php
/** HTTPS acceptance against SiteGround itself; no DNS cutover or TLS bypass. */
$home = getenv('HOME');
$auth = json_decode(file_get_contents($home . '/.cml-staging/preview-access.json'), true);
$bundle = json_decode(file_get_contents($home . '/.cml-staging/cml-migration/bundle.json'), true);
$base = 'https://preview.chinamudlogging.com';
$paths = [];
if (!defined('ABSPATH')) require $home . '/www/chinamudlogging.com/public_html/wp-load.php';
$base = rtrim(get_option('home'), '/');
foreach (get_posts(['post_type' => ['page','post','cml_product'], 'post_status' => 'publish', 'numberposts' => -1, 'lang' => '', 'meta_key' => '_cml_import_key']) as $post) $paths[wp_parse_url(get_permalink($post), PHP_URL_PATH)] = 'content';
foreach (get_terms(['taxonomy' => 'cml_family', 'hide_empty' => false, 'lang' => '']) as $term) $paths[wp_parse_url(get_term_link($term), PHP_URL_PATH)] = 'family';
foreach ($bundle['redirects'] as $source => $target) $paths[$source] = 'redirect';
foreach ($bundle['media'] as $asset) $paths[$asset['path']] = 'media';
foreach (get_option('cml_asset_redirects', []) as $path => $target) $paths[$path] = 'media';
$paths['/sitemap.xml'] = 'sitemap-redirect';
$results = [];
foreach ($paths as $path => $kind) {
    $handle = curl_init($base . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 40,
        CURLOPT_USERAGENT => 'ChinaMudLogging-Migration-Audit/1.0',
        CURLOPT_RESOLVE => ['preview.chinamudlogging.com:443:35.213.135.178', 'www.chinamudlogging.com:443:35.213.135.178'],
        CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if (str_contains($base, 'preview.')) curl_setopt($handle, CURLOPT_USERPWD, $auth['user'] . ':' . $auth['password']);
    $body = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $effective = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
    $expected = $kind === 'media' ? (get_option('cml_asset_redirects', [])[$path] ?? '') : $base . ($kind === 'redirect' ? $bundle['redirects'][$path] : $path);
    $correct_path = $kind === 'sitemap-redirect' ? $effective === $base . '/sitemap_index.xml' : rtrim($effective, '/') === rtrim($expected, '/');
    $metadata_errors = [];
    if ($kind === 'content' && is_string($body)) {
        preg_match_all('/<link\b[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $body, $canonical);
        if (($canonical[1] ?? []) !== [$base . $path]) $metadata_errors[] = 'canonical';
        if (preg_match_all('/<h1\b/i', $body) !== 1) $metadata_errors[] = 'h1';
        if (!preg_match('/<meta\b[^>]*name=["\']description["\'][^>]*content=["\'][^"\']+/i', $body)) $metadata_errors[] = 'description';
        $language = explode('/', trim($path, '/'))[0];
        if (in_array($language, ['zh','es','ru','ar','fr','de'], true)) {
            if (!preg_match('/<html\b[^>]*lang=["\']' . $language . '(?:-[A-Za-z]+)?["\']/i', $body)) $metadata_errors[] = 'language';
            if (!preg_match('/hreflang=["\']en(?:-[A-Za-z]+)?["\']/i', $body)) $metadata_errors[] = 'English alternate';
            if ($language === 'ar' && !preg_match('/<html\b[^>]*dir=["\']rtl["\']/i', $body)) $metadata_errors[] = 'direction';
        }
    }
    $results[] = ['path' => $path, 'kind' => $kind, 'status' => $status, 'correctPath' => $correct_path,
        'metadataErrors' => $metadata_errors, 'effectiveUrl' => $effective, 'redirectCount' => curl_getinfo($handle, CURLINFO_REDIRECT_COUNT),
        'fatalError' => $kind !== 'media' && $body !== false && (str_contains($body, 'Fatal error') || str_contains($body, '<b>Warning</b>')),
        'error' => curl_error($handle) ?: null];
    curl_close($handle);
}
$failed = array_values(array_filter($results, fn($r) => $r['status'] !== 200 || !$r['correctPath'] || $r['fatalError'] || $r['error'] || $r['metadataErrors'] || $r['redirectCount'] > 1));
$report = ['environment' => 'SiteGround PHP HTTPS, real certificate verification, protected staging', 'checked' => count($results), 'failed' => $failed, 'results' => $results];
file_put_contents($home . '/.cml-staging/http-results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['checked' => count($results), 'failed' => $failed], JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed ? 1 : 0);
