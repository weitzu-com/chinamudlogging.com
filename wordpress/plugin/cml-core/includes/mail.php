<?php
defined('ABSPATH') || exit;
// SMTP transport remains owned by WP Mail SMTP. Enable signing only after the
// website-specific public DKIM selector has been published and verified.
add_action('phpmailer_init', function ($mailer) {
    if (!get_option('cml_dkim_enabled', false)) return;
    $key = dirname(rtrim(ABSPATH, '/'), 3) . '/.cml-mail/dkim-private.pem';
    if (!is_readable($key)) throw new \PHPMailer\PHPMailer\Exception('Website DKIM signing key is unavailable.');
    $mailer->DKIM_domain = 'chinamudlogging.com';
    $mailer->DKIM_selector = 'cml20261104';
    $mailer->DKIM_identity = $mailer->From;
    $mailer->DKIM_private = $key;
    $mailer->DKIM_copyHeaderFields = false;
    $mailer->Sender = 'sales@chinamudlogging.com';
}, 100);

// Route only saved CML inquiry notifications directly to the existing recipient
// domain's verified Zoho MX over mandatory TLS. Other WordPress mail retains
// WP Mail SMTP's configured transport. No mailbox password or relay is used.
function cml_configure_inquiry_mx($mailer): void {
    if (!get_option('cml_direct_mx_enabled', false)) return;
    $recipients = $mailer->getToAddresses();
    if (count($recipients) !== 1 || strtolower($recipients[0][0]) !== 'sales@chinamudlogging.com') return;
    if (!preg_match('/^<cml-[a-f0-9-]{36}@chinamudlogging\.com>$/', $mailer->MessageID)) return;
    $mailer->isSMTP();
    $mailer->Host = 'mx.zoho.com;mx2.zoho.com;mx3.zoho.com';
    $mailer->Port = 25;
    $mailer->SMTPAuth = false;
    $mailer->Username = '';
    $mailer->Password = '';
    $mailer->SMTPSecure = 'tls';
    $mailer->SMTPAutoTLS = true;
    $mailer->Timeout = 12;
    $mailer->SMTPDebug = 0;
    $mailer->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
}
add_action('phpmailer_init', 'cml_configure_inquiry_mx', 300);
