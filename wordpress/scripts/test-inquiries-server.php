<?php
/** Complete the other three languages using a second actual network origin. */
$home = getenv('HOME');
$auth = json_decode(file_get_contents($home . '/.cml-staging/preview-access.json'), true);
$base = 'https://preview.chinamudlogging.com';
function cml_acceptance_http(string $path, ?array $payload = null): array {
    global $auth, $base;
    $h = curl_init($base . $path);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40,
        CURLOPT_RESOLVE => ['preview.chinamudlogging.com:443:35.213.135.178'],
        CURLOPT_USERPWD => $auth['user'] . ':' . $auth['password'],
        CURLOPT_USERAGENT => 'ChinaMudLogging-Acceptance/1.0']);
    if ($payload !== null) curl_setopt_array($h, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Origin: ' . $base]]);
    $body = curl_exec($h); $status = curl_getinfo($h, CURLINFO_HTTP_CODE); $error = curl_error($h); curl_close($h);
    if ($error || $status >= 400) throw new RuntimeException('Acceptance HTTP ' . $status . ': ' . $error . ' ' . $body);
    return [$status, json_decode($body, true)];
}
$file = $home . '/.cml-staging/inquiries-server.json';
$results = is_file($file) ? json_decode(file_get_contents($file), true) : [];
foreach (['ar','fr','de'] as $lang) {
    if (array_filter($results, fn($r) => $r['language'] === $lang && $r['saved'])) continue;
    [, $token] = cml_acceptance_http('/wp-json/cml/v1/form-token');
    $bytes = random_bytes(16); $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    $payload = ['nonce' => $token['nonce'], 'request_id' => $id, 'language' => $lang,
        'name' => 'WEBSITE ACCEPTANCE TEST', 'email' => 'test@example.invalid', 'company' => 'TEST ONLY', 'country' => 'TEST ONLY',
        'product' => 'CML acceptance ' . $lang, 'message' => 'TEST ONLY — authorized seven-language website receipt verification. No customer data.',
        'source' => '/contact-us/?test_marker=must-not-be-saved', 'consent' => true, 'website' => ''];
    [$status, $data] = cml_acceptance_http('/wp-json/cml/v1/inquiries', $payload);
    [$again_status, $again] = cml_acceptance_http('/wp-json/cml/v1/inquiries', $payload);
    $row = ['language' => $lang, 'status' => $status, 'saved' => $data['saved'], 'receipt' => $data['receipt'], 'duplicateSameReceipt' => $again_status === 200 && $again['receipt'] === $data['receipt']];
    $results[] = $row; file_put_contents($file, json_encode($results, JSON_PRETTY_PRINT)); echo json_encode($row) . PHP_EOL;
}
