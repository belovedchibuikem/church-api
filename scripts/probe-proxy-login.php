<?php

$jar = __DIR__.'/../storage/proxy-cookies.txt';
@unlink($jar);

function curlJson(string $url, string $jar, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    $httpHeaders = array_merge(['Accept: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $httpHeaders,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $bodyStart = strrpos($raw, '{') ?: 0;

    return ['code' => $code, 'json' => json_decode(substr($raw, $bodyStart), true)];
}

function xsrfFromJar(string $jar): ?string {
    if (! is_readable($jar)) {
        return null;
    }
    foreach (file($jar, FILE_IGNORE_NEW_LINES) as $line) {
        if (! str_contains($line, 'XSRF-TOKEN')) {
            continue;
        }
        $parts = explode("\t", $line);
        if (count($parts) >= 7) {
            return urldecode($parts[6]);
        }
    }

    return null;
}

$csrf = curlJson('http://localhost:3000/api/v1/auth/csrf-cookie', $jar);
$plain = $csrf['json']['data']['csrf_token'] ?? '';
$xsrf = xsrfFromJar($jar) ?? '';

$login = curlJson(
    'http://localhost:3000/api/v1/auth/login',
    $jar,
    [
        'Content-Type: application/json',
        'X-CSRF-TOKEN: '.$plain,
        'X-XSRF-TOKEN: '.$xsrf,
    ],
    json_encode([
        'email' => 'admin@familyhouse.demo',
        'password' => 'DemoPass!2026',
    ], JSON_THROW_ON_ERROR),
);

echo 'CSRF status: '.$csrf['code'].PHP_EOL;
echo 'Login status: '.$login['code'].PHP_EOL;
echo 'Login body: '.json_encode($login['json'], JSON_PRETTY_PRINT).PHP_EOL;
