<?php
require_once '/wordpress/wp-load.php';
require '/cml/extensions/cml-legacy-query.php';
class CML_Redirect_Proof extends RuntimeException {
    public function __construct(public string $location, public int $status) { parent::__construct('Captured redirect'); }
}
$assert = function ($ok, $label) { if (!$ok) throw new RuntimeException($label); };
$map = ['/home/' => '/', '/shop/' => '/product/', '/news/' => '/blog/', '/what-we-can-do/' => '/services/', '/why-choose-us/' => '/about-us/', '/cart/' => '/contact-us/', '/checkout/' => '/contact-us/', '/my-account/' => '/contact-us/'];
update_option('cml_redirects', $map);
$assert(has_action('template_redirect', 'cml_redirect_legacy_query') === 0, 'Run before the existing redirect handler');
add_filter('wp_redirect', function ($location, $status) { throw new CML_Redirect_Proof($location, $status); }, 10, 2);
$count = 0;
foreach (['q=ALS+2.2&utm_source=cml-72h', 'product=0.25%E2%80%9310+m%3E70+%C2%B0C', 'q=one&q=two&ref=a%2Fb%26c%3Dd', 'q=%D8%B7%D9%8A%D9%86&family=mud-logging-sensors'] as $query) {
    foreach ($map as $source => $target) {
        $_SERVER['REQUEST_URI'] = $source . '?' . $query;
        try { cml_redirect_legacy_query(); throw new RuntimeException('Expected redirect'); }
        catch (CML_Redirect_Proof $proof) {
            $assert($proof->status === 301 && $proof->location === home_url($target) . '?' . $query, 'Exact path/query preservation, encoding and one permanent redirect');
            $assert(wp_parse_url($proof->location, PHP_URL_HOST) === wp_parse_url(home_url(), PHP_URL_HOST), 'Same-origin destination');
            $count++;
        }
    }
}
foreach (['/shop/', '/shop/?', '/unknown/?q=ALS', '/sitemap.xml?q=ALS', '/wp-content/uploads/file.pdf?q=ALS'] as $request) {
    $_SERVER['REQUEST_URI'] = $request;
    cml_redirect_legacy_query();
    $count++;
}
foreach (['https://example.invalid/', '//example.invalid/', '/product/?existing=1', '/product/#section', "/product/\r\n", '/shop/'] as $bad) {
    $assert(cml_legacy_query_target(['/shop/' => $bad], '/shop/', 'q=ALS') === null, 'Unsafe or separately scoped mappings untouched');
    $count++;
}
$assert(cml_legacy_query_target($map, '/shop/', "q=ALS\r\nInjected: x") === null, 'Raw header controls rejected');
$assert(get_option('cml_redirects') === $map, 'Existing mapping unchanged');
$count += 2;
file_put_contents('/cml-query-tests/result.json', wp_json_encode(['passed' => true, 'scenarios' => $count, 'legacyMappings' => count($map)], JSON_PRETTY_PRINT));
