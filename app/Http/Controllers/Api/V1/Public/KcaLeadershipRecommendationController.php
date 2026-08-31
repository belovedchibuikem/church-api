<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\KcaLeadershipRecommendation;
use App\Support\Api\ApiResponse;
use App\Support\Kca\SubmitKcaLeadershipRecommendationAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class KcaLeadershipRecommendationController extends Controller
{
    public function show(Request $request, string $token): JsonResponse
    {
        $row = $this->find($token);

        return ApiResponse::success($request, $this->payload($row, includeStatement: false));
    }

    public function submit(Request $request, string $token, SubmitKcaLeadershipRecommendationAction $action): JsonResponse
    {
        $data = $request->validate([
            'statement' => ['required', 'string', 'min:1', 'max:5000'],
        ]);
        try {
            $row = $action->handle($token, (string) $data['statement']);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return ApiResponse::success($request, $this->payload($row, includeStatement: true));
    }

    private function find(string $token): KcaLeadershipRecommendation
    {
        $normalized = strtolower(trim($token));
        if (! preg_match('/\A[a-f0-9]{64}\z/', $normalized)) {
            throw new AccessDeniedHttpException('This recommendation link is not valid.');
        }

        return KcaLeadershipRecommendation::query()
            ->where('token_hash', hash('sha256', $normalized))
            ->first() ?? throw new AccessDeniedHttpException('This recommendation link is not valid.');
    }

    /** @return array<string, mixed> */
    private function payload(KcaLeadershipRecommendation $row, bool $includeStatement): array
    {
        return [
            'id' => $row->public_id,
            'status' => $row->status,
            'recommender_name' => $row->recommender_name,
            'recommender_role' => $row->recommender_role,
            'submitted_at' => $row->submitted_at?->utc()->toIso8601String(),
            'statement' => $includeStatement ? $row->statement : null,
        ];
    }
}
