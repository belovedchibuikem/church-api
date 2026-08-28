<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AddPressPublicationContributorRequest;
use App\Http\Requests\Api\V1\Admin\AssignPressPublicationIsbnRequest;
use App\Http\Requests\Api\V1\Admin\CreatePressPublicationRequest;
use App\Http\Requests\Api\V1\Admin\CreatePressTranslationRequest;
use App\Http\Requests\Api\V1\Admin\TransitionPressPublicationRequest;
use App\Http\Requests\Api\V1\Admin\TransitionPressTranslationRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\FileAsset;
use App\Models\Person;
use App\Models\PressPublication;
use App\Models\PressPublicationContributor;
use App\Models\PressTranslation;
use App\Press\PressContributorRole;
use App\Press\PressPublicationData;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationStatus;
use App\Press\PressTranslationData;
use App\Press\PressTranslationStatus;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Press\AddPressPublicationContributorAction;
use App\Support\Press\AssignPressPublicationIsbnAction;
use App\Support\Press\CreateMachinePressTranslationAction;
use App\Support\Press\CreatePressPublicationAction;
use App\Support\Press\TransitionPressPublicationAction;
use App\Support\Press\TransitionPressTranslationAction;
use Illuminate\Http\JsonResponse;

class PressOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function storePublication(CreatePressPublicationRequest $request, CreatePressPublicationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $cover = $request->validated('cover_file_asset_id') === null ? null : FileAsset::query()->where('public_id', $request->validated('cover_file_asset_id'))->firstOrFail();
        $content = $request->validated('content_file_asset_id') === null ? null : FileAsset::query()->where('public_id', $request->validated('content_file_asset_id'))->firstOrFail();
        $publication = $this->execute(fn (): PressPublication => $action->handle(
            new PressPublicationData(
                title: (string) $request->validated('title'),
                publisherName: (string) $request->validated('publisher_name'),
                languageCode: (string) $request->validated('language_code'),
                format: PressPublicationFormat::from((string) $request->validated('format')),
                subtitle: $request->validated('subtitle'),
                edition: $request->validated('edition'),
                publicationDate: $request->validated('publication_date'),
                copyrightYear: $request->validated('copyright_year') === null ? null : (int) $request->validated('copyright_year'),
                pageCount: $request->validated('page_count') === null ? null : (int) $request->validated('page_count'),
                category: $request->validated('category'),
                description: $request->validated('description'),
                coverFileAsset: $cover,
                contentFileAsset: $content,
                priceMinor: $request->validated('price_minor') === null ? null : (int) $request->validated('price_minor'),
                currencyCode: $request->validated('currency_code'),
            ),
            $context->actor($request),
            (string) $request->validated('idempotency_key'),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($publication))->resolve($request), status: 201);
    }

    public function transitionPublication(TransitionPressPublicationRequest $request, string $publication, TransitionPressPublicationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = PressPublication::query()->where('public_id', $publication)->firstOrFail();
        $updated = $this->execute(fn (): PressPublication => $action->handle(
            $target,
            PressPublicationStatus::from((string) $request->validated('status')),
            $context->actor($request),
            (string) $request->validated('reason_code'),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function assignIsbn(AssignPressPublicationIsbnRequest $request, string $publication, AssignPressPublicationIsbnAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = PressPublication::query()->where('public_id', $publication)->firstOrFail();
        $updated = $this->execute(fn (): PressPublication => $action->handle(
            $target,
            (string) $request->validated('isbn'),
            $context->actor($request),
            (string) $request->validated('reason_code'),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function addContributor(AddPressPublicationContributorRequest $request, string $publication, AddPressPublicationContributorAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = PressPublication::query()->where('public_id', $publication)->firstOrFail();
        $person = Person::query()->where('public_id', $request->validated('person_id'))->firstOrFail();
        $contributor = $this->execute(fn (): PressPublicationContributor => $action->handle(
            $target,
            $person,
            PressContributorRole::from((string) $request->validated('role')),
            $context->actor($request),
        ));
        $contributor->load(['publication:id,public_id', 'person:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($contributor))->resolve($request), status: 201);
    }

    public function storeTranslation(CreatePressTranslationRequest $request, string $publication, CreateMachinePressTranslationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = PressPublication::query()->where('public_id', $publication)->firstOrFail();
        $translation = $this->execute(fn (): PressTranslation => $action->handle(
            $target,
            new PressTranslationData(
                targetLanguageCode: (string) $request->validated('target_language_code'),
                translatedTitle: (string) $request->validated('translated_title'),
                translatedSubtitle: $request->validated('translated_subtitle'),
                translatedDescription: $request->validated('translated_description'),
                translatedContent: $request->validated('translated_content'),
            ),
            $context->actor($request),
            (string) $request->validated('idempotency_key'),
        ));
        $translation->load(['publication:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($translation))->resolve($request), status: 201);
    }

    public function transitionTranslation(TransitionPressTranslationRequest $request, string $translation, TransitionPressTranslationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = PressTranslation::query()->where('public_id', $translation)->firstOrFail();
        $updated = $this->execute(fn (): PressTranslation => $action->handle(
            $target,
            PressTranslationStatus::from((string) $request->validated('status')),
            $context->actor($request),
            (string) $request->validated('reason_code'),
        ));
        $updated->load(['publication:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }
}
