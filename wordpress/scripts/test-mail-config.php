<?php
/** Configuration assertions only: never sends mail or modifies saved inquiries. */
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('CLI only.');
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
$old = get_option('cml_direct_mx_enabled', false); $results = [];
try {
    update_option('cml_direct_mx_enabled', true);
    foreach ([['sales@chinamudlogging.com', true], ['unrelated@example.com', false]] as [$recipient, $expected]) {
        $m = new \PHPMailer\PHPMailer\PHPMailer();
        $m->addAddress($recipient); $m->MessageID = '<cml-416efc56-2124-4e61-8ec3-160a93ec1dd7@chinamudlogging.com>';
        cml_configure_inquiry_mx($m);
        $results[] = ($m->Mailer === 'smtp') === $expected && (!$expected || ($m->SMTPSecure === 'tls' && !$m->SMTPAuth && $m->Port === 25));
    }
    $m = new \PHPMailer\PHPMailer\PHPMailer(); $m->addAddress('sales@chinamudlogging.com');
    $m->MessageID = '<other-wordpress-notification@example.com>'; cml_configure_inquiry_mx($m);
    $results[] = $m->Mailer === 'mail';
} finally { update_option('cml_direct_mx_enabled', $old); }
echo wp_json_encode(['passed' => count(array_filter($results)), 'total' => count($results)]) . PHP_EOL;
if (in_array(false, $results, true)) throw new RuntimeException('Inquiry SMTP configuration assertion failed.');
