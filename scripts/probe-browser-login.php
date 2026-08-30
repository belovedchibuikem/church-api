<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$csrfRequest = Request::create('/api/v1/auth/csrf-cookie', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
$csrfResponse = $kernel->handle($csrfRequest);
$csrfBody = json_decode($csrfResponse->getContent(), true);
$token = $csrfBody['data']['csrf_token'] ?? '';

$loginRequest = Request::create(
    '/api/v1/auth/login',
    'POST',
    server: [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CSRF_TOKEN' => $token,
    ],
    cookies: $csrfRequest->cookies->all(),
    content: json_encode([
        'email' => 'admin@familyhouse.demo',
        'password' => 'DemoPass!2026',
    ], JSON_THROW_ON_ERROR),
);

$loginResponse = $kernel->handle($loginRequest);
echo 'CSRF status: '.$csrfResponse->getStatusCode().PHP_EOL;
echo 'Login status: '.$loginResponse->getStatusCode().PHP_EOL;
echo 'Login body: '.$loginResponse->getContent().PHP_EOL;
$kernel->terminate($loginRequest, $loginResponse);
