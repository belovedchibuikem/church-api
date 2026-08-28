<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\AuthenticateMobileAccessToken;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureActiveBrowserSecuritySession;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\EnsureVerifiedEmail;
use App\Support\Api\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignCorrelationId::class);
        $middleware->alias([
            'auth.mobile' => AuthenticateMobileAccessToken::class,
            'active.account' => EnsureActiveAccount::class,
            'active.browser.session' => EnsureActiveBrowserSecuritySession::class,
            'mfa.recent' => EnsureRecentMfa::class,
            'verified.email' => EnsureVerifiedEmail::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReportDuplicates();
        $exceptions->render(
            fn (Throwable $exception, Request $request): ?JsonResponse => ApiExceptionRenderer::render(
                $request,
                $exception,
            ),
        );
    })->create();
