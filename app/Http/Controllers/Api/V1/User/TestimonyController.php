<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\StoreUserTestimonyRequest;
use App\Http\Requests\Api\V1\User\UpdateUserTestimonyRequest;
use App\Http\Resources\Api\V1\User\TestimonyResource;
use App\Models\ChurchMembership;
use App\Models\Testimony;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TestimonyController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function index(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $testimonies = Testimony::query()
            ->where('person_id', $person->getKey())
            ->latest('submitted_at')
            ->limit(100)
            ->get();

        return ApiResponse::success(
            $request,
            TestimonyResource::collection($testimonies)->resolve($request),
        );
    }

    public function show(Request $request, string $testimony): JsonResponse
    {
        return ApiResponse::success(
            $request,
            TestimonyResource::make($this->owned($request, $testimony))->resolve($request),
        );
    }

    public function store(StoreUserTestimonyRequest $request): JsonResponse
    {
        $person = $this->person($request);
        $validated = $request->validated();
        $churchId = ChurchMembership::query()
            ->where('person_id', $person->getKey())
            ->where('active_marker', 1)
            ->value('church_id');

        $row = new Testimony;
        $row->forceFill([
            'person_id' => $person->getKey(),
            'church_id' => $churchId,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => 'pending',
            'submitted_at' => now()->utc(),
        ])->save();

        return ApiResponse::success(
            $request,
            TestimonyResource::make($row)->resolve($request),
            status: 201,
        );
    }

    public function update(UpdateUserTestimonyRequest $request, string $testimony): JsonResponse
    {
        $row = $this->owned($request, $testimony);
        $this->assertPending($row);
        $validated = $request->validated();
        $row->forceFill([
            'title' => $validated['title'],
            'body' => $validated['body'],
        ])->save();

        return ApiResponse::success(
            $request,
            TestimonyResource::make($row)->resolve($request),
        );
    }

    public function destroy(Request $request, string $testimony): JsonResponse
    {
        $row = $this->owned($request, $testimony);
        $this->assertPending($row);
        $row->delete();

        return ApiResponse::success($request, ['id' => $testimony, 'deleted' => true]);
    }

    private function owned(Request $request, string $publicId): Testimony
    {
        $person = $this->person($request);

        return Testimony::query()
            ->where('person_id', $person->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function assertPending(Testimony $testimony): void
    {
        if ($testimony->status !== 'pending') {
            throw new UnprocessableEntityHttpException('Only pending testimonies can be changed.');
        }
    }
}
