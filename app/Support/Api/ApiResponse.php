<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        Request $request,
        array $data,
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        $correlationId = $request->attributes->get('correlation_id');

        return response()->json([
            'data' => $data,
            'meta' => [
                'api_version' => 'v1',
                'timestamp' => now()->utc()->toIso8601String(),
                ...$meta,
            ],
            'correlation_id' => $correlationId,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $headers
     */
    public static function error(
        Request $request,
        string $code,
        string $message,
        array $details = [],
        int $status = 400,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
            'meta' => [
                'api_version' => 'v1',
                'timestamp' => now()->utc()->toIso8601String(),
            ],
            'correlation_id' => $request->attributes->get('correlation_id'),
        ], $status, $headers);
    }
}
