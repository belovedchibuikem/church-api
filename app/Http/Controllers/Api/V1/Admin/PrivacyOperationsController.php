<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BeginDataExportRequest;
use App\Http\Requests\Api\V1\Admin\CompleteDataExportRequest;
use App\Http\Requests\Api\V1\Admin\SubmitDataSubjectRequestRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\DataSubjectRequest;
use App\Models\FileAsset;
use App\Models\Person;
use App\Privacy\Actions\BeginDataExportRequestAction;
use App\Privacy\Actions\CompleteDataExportRequestAction;
use App\Privacy\Actions\ExpireDataExportRequestAction;
use App\Privacy\Actions\SubmitDataSubjectRequestAction;
use App\Privacy\DataSubjectRequestType;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function submit(SubmitDataSubjectRequestRequest $request, SubmitDataSubjectRequestAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $person = Person::query()->where('public_id', $request->validated('person_id'))->firstOrFail();
        $result = $this->execute(fn (): DataSubjectRequest => $action->handle(
            $person,
            DataSubjectRequestType::from((string) $request->validated('request_type')),
            (string) $request->validated('idempotency_key'),
            $request->validated('notes'),
            $context->actor($request),
        ));
        $result->load(['person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($result))->resolve($request), status: 201);
    }

    public function beginExport(BeginDataExportRequest $request, string $dataSubjectRequest, BeginDataExportRequestAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = DataSubjectRequest::query()->where('public_id', $dataSubjectRequest)->firstOrFail();
        $scope = null;
        if ($request->validated('scope_type') !== null && $request->validated('scope_key') !== null) {
            $scope = new ScopeReference((string) $request->validated('scope_type'), (string) $request->validated('scope_key'));
        }
        $updated = $this->execute(fn (): DataSubjectRequest => $action->handle(
            $target,
            (array) $request->validated('data_categories'),
            $context->actor($request),
            $scope,
        ));
        $updated->load(['person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function completeExport(CompleteDataExportRequest $request, string $dataSubjectRequest, CompleteDataExportRequestAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = DataSubjectRequest::query()->where('public_id', $dataSubjectRequest)->firstOrFail();
        $fileAsset = FileAsset::query()->where('public_id', $request->validated('file_asset_id'))->firstOrFail();
        $updated = $this->execute(fn (): DataSubjectRequest => $action->handle(
            $target,
            $fileAsset,
            CarbonImmutable::parse((string) $request->validated('expires_at')),
            $context->actor($request),
        ));
        $updated->load(['person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function expireExport(Request $request, string $dataSubjectRequest, ExpireDataExportRequestAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = DataSubjectRequest::query()->where('public_id', $dataSubjectRequest)->firstOrFail();
        $updated = $this->execute(fn (): DataSubjectRequest => $action->handle($target, $context->actor($request)));
        $updated->load(['person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }
}
