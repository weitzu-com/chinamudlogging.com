<?php
/** Install only this site's canonical and historical static-media redirects. */
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('CLI only.');
$file = ABSPATH . '.htaccess';
$existing = is_file($file) ? file_get_contents($file) : '';
$backup = dirname(rtrim(ABSPATH, '/'), 3) . '/.cml-staging/htaccess-before-' . gmdate('YmdHis');
if (file_put_contents($backup, $existing) === false) throw new RuntimeException('Cannot back up redirects.');
chmod($backup, 0600);
$rules = ['# BEGIN CML migration', '<IfModule mod_rewrite.c>', 'RewriteEngine On',
    'RewriteCond %{HTTP_HOST} ^(?:www\.)?chinamudlogging\.com$ [NC]',
    'RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/',
    'RewriteCond %{HTTPS} !=on [OR]',
    'RewriteCond %{HTTP_HOST} !^www\.chinamudlogging\.com$ [NC]',
    'RewriteRule ^ https://www.chinamudlogging.com%{REQUEST_URI} [R=301,L,NE]'];
foreach (get_option('cml_asset_redirects', []) as $source => $target) {
    if (!str_starts_with($source, '/wp-content/uploads/') || str_starts_with($source, '/wp-content/uploads/cml/')) continue;
    $path = wp_parse_url($target, PHP_URL_PATH);
    if (!$path || !str_starts_with($path, '/wp-content/uploads/cml/')) throw new RuntimeException('Unexpected media target.');
    $rules[] = 'RewriteRule ^' . preg_quote(ltrim($source, '/'), '#') . '$ ' . $path . ' [R=301,L,NE]';
}
$rules[] = '</IfModule>'; $rules[] = '# END CML migration';
$existing = preg_replace('/# BEGIN CML migration\R.*?# END CML migration\R?/s', '', $existing);
if (file_put_contents($file, implode("\n", $rules) . "\n" . $existing) === false) throw new RuntimeException('Cannot install redirects.');
echo 'Canonical rules and historical media redirects installed; prior file retained outside webroot.' . PHP_EOL;
