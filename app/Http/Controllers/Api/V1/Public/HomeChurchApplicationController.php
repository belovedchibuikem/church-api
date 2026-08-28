<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Exceptions\HomeChurchApplicationIdempotencyConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\StoreHomeChurchApplicationRequest;
use App\Http\Resources\Api\V1\Public\HomeChurchApplicationSubmissionResource;
use App\Support\Api\ApiResponse;
use App\Support\Church\SubmitPublicHomeChurchApplicationAction;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class HomeChurchApplicationController extends Controller
{
    public function __invoke(
        StoreHomeChurchApplicationRequest $request,
        SubmitPublicHomeChurchApplicationAction $submitApplication,
    ): JsonResponse {
        try {
            $submission = $submitApplication->handle($request->toData());
        } catch (HomeChurchApplicationIdempotencyConflictException $exception) {
            throw new ConflictHttpException(previous: $exception);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException(previous: $exception);
        }

        $resource = new HomeChurchApplicationSubmissionResource($submission->application);

        return ApiResponse::success(
            $request,
            $resource->resolve($request),
            ['idempotent_replay' => ! $submission->wasCreated],
            $submission->wasCreated ? 201 : 200,
        );
    }
}
