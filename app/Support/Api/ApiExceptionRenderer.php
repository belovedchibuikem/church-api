<?php

namespace App\Support\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionRenderer
{
    public static function render(Request $request, Throwable $exception): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return ApiResponse::error(
                $request,
                'VALIDATION_FAILED',
                'The request data is invalid.',
                ['fields' => $exception->errors()],
                422,
            );
        }

        if ($exception instanceof AuthenticationException) {
            return ApiResponse::error(
                $request,
                'AUTH_UNAUTHENTICATED',
                'Authentication is required.',
                status: 401,
            );
        }

        if ($exception instanceof AuthorizationException || $exception instanceof AccessDeniedHttpException) {
            return ApiResponse::error(
                $request,
                'AUTH_PERMISSION_DENIED',
                'You are not authorized to perform this action.',
                status: 403,
            );
        }

        if ($exception instanceof ThrottleRequestsException) {
            return ApiResponse::error(
                $request,
                'RATE_LIMIT_EXCEEDED',
                'Too many requests. Please try again later.',
                status: 429,
                headers: $exception->getHeaders(),
            );
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return ApiResponse::error(
                $request,
                'RESOURCE_NOT_FOUND',
                'The requested resource was not found.',
                status: 404,
            );
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return ApiResponse::error(
                $request,
                'METHOD_NOT_ALLOWED',
                'The HTTP method is not allowed for this resource.',
                status: 405,
                headers: $exception->getHeaders(),
            );
        }

        if ($exception instanceof HttpExceptionInterface) {
            return self::renderHttpException($request, $exception);
        }

        return ApiResponse::error(
            $request,
            'INTERNAL_SERVER_ERROR',
            'An unexpected error occurred.',
            status: 500,
        );
    }

    private static function renderHttpException(
        Request $request,
        HttpExceptionInterface $exception,
    ): JsonResponse {
        [$code, $message] = match ($exception->getStatusCode()) {
            400 => ['INVALID_REQUEST', 'The request could not be processed.'],
            401 => ['AUTH_UNAUTHENTICATED', 'Authentication is required.'],
            403 => ['AUTH_PERMISSION_DENIED', 'You are not authorized to perform this action.'],
            404 => ['RESOURCE_NOT_FOUND', 'The requested resource was not found.'],
            409 => ['RESOURCE_CONFLICT', 'The request conflicts with the current resource state.'],
            413 => ['PAYLOAD_TOO_LARGE', 'The request payload is too large.'],
            415 => ['UNSUPPORTED_MEDIA_TYPE', 'The request media type is not supported.'],
            422 => ['VALIDATION_FAILED', 'The request data is invalid.'],
            429 => ['RATE_LIMIT_EXCEEDED', 'Too many requests. Please try again later.'],
            503 => ['SERVICE_UNAVAILABLE', 'The service is temporarily unavailable.'],
            default => ['HTTP_ERROR', 'The request could not be completed.'],
        };

        return ApiResponse::error(
            $request,
            $code,
            $message,
            status: $exception->getStatusCode(),
            headers: $exception->getHeaders(),
        );
    }
}
