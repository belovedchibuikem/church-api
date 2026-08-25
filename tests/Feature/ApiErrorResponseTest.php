<?php

namespace Tests\Feature;

use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    public function test_returns_normalized_404_without_an_accept_header(): void
    {
        $response = $this->get('/api/v1/does-not-exist');

        $correlationId = $response->headers->get('X-Correlation-ID');

        $response
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('error.message', 'The requested resource was not found.')
            ->assertJsonPath('correlation_id', $correlationId)
            ->assertJsonMissingPath('exception');
        $this->assertTrue(Str::isUuid($correlationId));
    }

    public function test_returns_normalized_405_for_an_unsupported_method(): void
    {
        $response = $this->postJson('/api/v1/health');

        $response
            ->assertMethodNotAllowed()
            ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED')
            ->assertHeader('Allow', 'GET, HEAD');
    }

    public function test_returns_normalized_422_with_field_errors_for_invalid_data(): void
    {
        Route::post('/api/v1/testing/validation', function (Request $request): JsonResponse {
            $validated = $request->validate([
                'email' => ['required', 'email'],
            ]);

            return ApiResponse::success($request, $validated);
        });

        $response = $this->postJson('/api/v1/testing/validation');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields.email.0', 'The email field is required.')
            ->assertJsonMissingPath('data');
    }

    public function test_returns_normalized_401_for_an_unauthenticated_request(): void
    {
        Route::get('/api/v1/testing/authentication', function (): never {
            throw new AuthenticationException;
        });

        $response = $this->getJson('/api/v1/testing/authentication');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_returns_normalized_403_for_a_denied_request(): void
    {
        Route::get('/api/v1/testing/authorization', function (): never {
            throw new AuthorizationException;
        });

        $response = $this->getJson('/api/v1/testing/authorization');

        $response
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTH_PERMISSION_DENIED');
    }

    public function test_returns_normalized_429_after_the_public_limit_is_exceeded(): void
    {
        config()->set('api.rate_limits.public_per_minute', 2);

        for ($requestNumber = 1; $requestNumber <= 2; $requestNumber++) {
            $this->getJson('/api/v1')->assertOk();
        }

        $response = $this->getJson('/api/v1');

        $response
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED')
            ->assertHeader('Retry-After');
    }

    public function test_returns_safe_500_without_leaking_exception_details(): void
    {
        Exceptions::fake();
        Route::get('/api/v1/testing/failure', function (): never {
            throw new RuntimeException('Database password leaked from C:\\secrets\\production.env');
        });

        $response = $this->getJson('/api/v1/testing/failure');

        $response
            ->assertInternalServerError()
            ->assertJsonPath('error.code', 'INTERNAL_SERVER_ERROR')
            ->assertJsonPath('error.message', 'An unexpected error occurred.')
            ->assertDontSee('Database password', false)
            ->assertDontSee('production.env', false)
            ->assertJsonMissingPath('exception');
        Exceptions::assertReported(RuntimeException::class);
    }
}
