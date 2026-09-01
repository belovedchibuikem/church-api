<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\CompleteBiblePlanDayRequest;
use App\Http\Requests\Api\V1\User\EnrollBiblePlanRequest;
use App\Http\Requests\Api\V1\User\UpdateBibleReadingPositionRequest;
use App\Support\Api\ApiResponse;
use App\Support\Bible\BibleProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserBibleController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function __construct(private readonly BibleProgressService $progress) {}

    public function progress(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->progress->snapshot($this->person($request)));
    }

    public function enroll(EnrollBiblePlanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return ApiResponse::success(
            $request,
            $this->progress->enroll(
                $this->person($request),
                $request->resolvedPlanCode() ?? '',
                $validated['started_on'] ?? null,
                $validated['timezone'] ?? null,
            ),
            status: 201,
        );
    }

    public function completeDay(CompleteBiblePlanDayRequest $request, string $enrollment, int $day): JsonResponse
    {
        return ApiResponse::success(
            $request,
            $this->progress->completeDay($this->person($request), $enrollment, $day),
        );
    }

    public function position(UpdateBibleReadingPositionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return ApiResponse::success(
            $request,
            $this->progress->rememberPosition(
                $this->person($request),
                $validated['book'],
                (int) $validated['chapter'],
            ),
        );
    }
}
