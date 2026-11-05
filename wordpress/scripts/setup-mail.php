<?php
/** One-time website-only DKIM + SiteGround's host-authorized local MTA route. */
if (!defined('ABSPATH') || !(defined('WP_CLI') && WP_CLI)) throw new RuntimeException('Run with WP-CLI.');
$directory = dirname(rtrim(ABSPATH, '/'), 3) . '/.cml-mail';
if (!is_dir($directory)) mkdir($directory, 0700, true);
$file = $directory . '/dkim-private.pem';
if (!is_file($file)) {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if (!$key || !openssl_pkey_export_to_file($key, $file)) throw new RuntimeException('DKIM key generation failed.');
    chmod($file, 0600);
}
$key = openssl_pkey_get_private(file_get_contents($file));
$details = openssl_pkey_get_details($key);
$public = preg_replace('/-----[^-]+-----|\s+/', '', $details['key']);
file_put_contents($directory . '/dkim-dns.json', json_encode([
    'name' => 'cml20261104._domainkey.chinamudlogging.com', 'type' => 'TXT',
    'content' => 'v=DKIM1; k=rsa; p=' . $public,
], JSON_PRETTY_PRINT));
chmod($directory . '/dkim-dns.json', 0600);
$options = get_option('wp_mail_smtp', []);
$options['mail'] = array_merge($options['mail'] ?? [], [
    'from_email' => 'website@chinamudlogging.com', 'from_name' => 'ChinaMudLogging website',
    'mailer' => 'mail', 'from_email_force' => true, 'from_name_force' => true, 'return_path' => true,
]);
update_option('wp_mail_smtp', $options);
update_option('cml_recipient', 'sales@chinamudlogging.com');
// Both switches remain off until DNS propagation and a test message are checked.
update_option('cml_mail_enabled', false);
update_option('cml_dkim_enabled', false);
echo 'Website local mail transport configured; DKIM public record prepared. Notifications remain disabled pending verification.' . PHP_EOL;
