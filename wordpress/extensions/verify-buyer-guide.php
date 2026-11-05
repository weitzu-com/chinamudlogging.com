<?php
/** Read-only public HTTP checks. Run through WP-CLI from the dedicated host, without login cookies. */
if (!defined('WP_CLI') || !WP_CLI) exit;
$base = 'https://www.chinamudlogging.com';
$results = []; $links = [];
foreach (['/product/' => true, '/product-category/mud-logging-units/' => true, '/product/?q=pressure' => false, '/product/?catalog_page=2' => false, '/product-category/mud-logging-sensors/' => false, '/zh/product-zh/' => false] as $path => $expected) {
    $response = wp_remote_get($base . $path, ['timeout' => 30, 'cookies' => [], 'headers' => ['Cache-Control' => 'no-cache']]);
    $html = wp_remote_retrieve_body($response);
    $status = wp_remote_retrieve_response_code($response);
    $guide = substr_count($html, 'id="cml-buyer-heading"');
    if ($status !== 200 || $guide !== (int)$expected || wp_remote_retrieve_header($response, 'sg-captcha')) WP_CLI::error('Unexpected HTTP/content at ' . $path);
    $dom = new DOMDocument(); @$dom->loadHTML($html); $xp = new DOMXPath($dom);
    $h1 = $xp->query('//h1')->length;
    $canonical = $xp->query('//link[@rel="canonical"]')->item(0)?->getAttribute('href');
    if ($h1 !== 1 || !$canonical) WP_CLI::error('Heading/canonical at ' . $path);
    $results[] = ['path' => $path, 'http' => $status, 'guideCount' => $guide, 'h1' => $h1, 'canonical' => $canonical, 'title' => $xp->query('//title')->item(0)?->textContent];
    foreach ($xp->query('//section[contains(concat(" ",normalize-space(@class)," ")," cml-buyer ")]//a[@href]') as $node) {
        $url = $node->getAttribute('href');
        if (str_starts_with($url, $base . '/')) $links[$url] = true;
    }
    usleep(750000);
}
foreach (array_keys($links) as $url) {
    $response = wp_remote_get($url, ['timeout' => 30, 'cookies' => []]);
    $status = wp_remote_retrieve_response_code($response);
    if ($status !== 200 || wp_remote_retrieve_header($response, 'sg-captcha')) WP_CLI::error('Linked URL failed ' . $url);
    $results[] = ['linkedUrl' => $url, 'http' => $status];
    usleep(750000);
}
echo wp_json_encode(['verifiedAt' => gmdate('c'), 'publicGuestPages' => 6, 'guideLinks' => count($links), 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
