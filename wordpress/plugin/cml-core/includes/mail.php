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
