<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AddPressPublicationContributorRequest;
use App\Http\Requests\Api\V1\Admin\AssignPressPublicationIsbnRequest;
use App\Http\Requests\Api\V1\Admin\CreatePressPublicationRequest;
use App\Http\Requests\Api\V1\Admin\CreatePressTranslationRequest;
use App\Http\Requests\Api\V1\Admin\SchedulePressPublicationRequest;
use App\Http\Requests\Api\V1\Admin\StorePressPublicationAssetRequest;
use App\Http\Requests\Api\V1\Admin\StorePressPublicationReviewRequest;
use App\Http\Requests\Api\V1\Admin\TransitionPressPublicationRequest;
use App\Http\Requests\Api\V1\Admin\TransitionPressTranslationRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePressPublicationRequest;
use App\Http\Requests\Api\V1\Admin\UpsertPressAuthorRequest;
use App\Http\Resources\Api\V1\Admin\PressPublicationAdminResource;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\FileAsset;
use App\Models\Person;
use App\Models\PressAuthor;
use App\Models\PressPublication;
use App\Models\PressPublicationAsset;
use App\Models\PressPublicationContributor;
use App\Models\PressPublicationReview;
use App\Models\PressTranslation;
use App\Press\PressAssetFormat;
use App\Press\PressContributorRole;
use App\Press\PressPublicationData;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationStatus;
use App\Press\PressPublicationType;
use App\Press\PressPublicationVisibility;
use App\Press\PressReviewDecision;
use App\Press\PressReviewStage;
use App\Press\PressTranslationData;
use App\Press\PressTranslationStatus;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Identity\PersonDisplayName;
use App\Support\Press\AddPressPublicationContributorAction;
use App\Support\Press\AssignPressPublicationIsbnAction;
use App\Support\Press\CreateMachinePressTranslationAction;
use App\Support\Press\CreatePressPublicationAction;
use App\Support\Press\DeletePressPublicationDraftAction;
use App\Support\Press\SchedulePressPublicationAction;
use App\Support\Press\StorePressPublicationAssetAction;
use App\Support\Press\StorePressPublicationReviewAction;
use App\Support\Press\TransitionPressPublicationAction;
use App\Support\Press\TransitionPressTranslationAction;
use App\Support\Press\UpdatePressPublicationAction;
use App\Support\Press\UpsertPressAuthorAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PressOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function storePublication(CreatePressPublicationRequest $request, CreatePressPublicationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $publication = $this->execute(fn (): PressPublication => $action->handle(
            $this->publicationData($request),
            $context->actor($request),
            (string) $request->validated('idempotency_key'),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($publication))->resolve($request), status: 201);
    }

    public function showPublication(Request $request, string $publication, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = $this->publication($publication);
        $target->load([
            ...PersonDisplayName::eager('contributors.person'),
            'assets.fileAsset',
            'contentFileAsset',
            'coverFileAsset',
            ...PersonDisplayName::eager('reviews.reviewer'),
            'translations',
        ]);

        return ApiResponse::success($request, (new PressPublicationAdminResource($target))->resolve($request));
    }

    public function updatePublication(UpdatePressPublicationRequest $request, string $publication, UpdatePressPublicationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = $this->publication($publication);
        $updated = $this->execute(fn (): PressPublication => $action->handle(
            $target,
            $this->publicationData($request),
            $context->actor($request),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function destroyPublication(Request $request, string $publication, DeletePressPublicationDraftAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = $this->publication($publication);
        $this->execute(fn () => $action->handle($target));

        return ApiResponse::success($request, ['id' => $publication, 'deleted' => true]);
    }

    public function schedulePublication(SchedulePressPublicationRequest $request, string $publication, SchedulePressPublicationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = $this->publication($publication);
        $updated = $this->execute(fn (): PressPublication => $action->handle(
            $target,
            $context->actor($request),
            $request->validated('scheduled_publish_at'),
            $request->validated('scheduled_unpublish_at'),
            (string) $request->validated('reason_code'),
        ));

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function transitionPublication(TransitionPressPublicationRequest $request, string $publication, TransitionPressPublicationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = $this->publication($publication);
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
        $target = $this->publication($publication);
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
        $target = $this->publication($publication);
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

    public function storeAsset(StorePressPublicationAssetRequest $request, string $publication, StorePressPublicationAssetAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = $this->publication($publication);
        $file = FileAsset::query()->where('public_id', $request->validated('file_asset_id'))->firstOrFail();
        $asset = $this->execute(fn (): PressPublicationAsset => $action->handle(
            $target,
            $file,
            PressAssetFormat::from((string) $request->validated('asset_format')),
            $context->actor($request),
            (bool) $request->validated('is_required', false),
            $request->validated('label'),
            $request->validated('language_code'),
        ));
        $asset->load(['publication:id,public_id,title', 'fileAsset:id,public_id']);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($asset))->resolve($request), status: 201);
    }

    public function storeReview(StorePressPublicationReviewRequest $request, string $publication, StorePressPublicationReviewAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = $this->publication($publication);
        $reviewer = $request->validated('reviewer_person_id') === null
            ? null
            : Person::query()->where('public_id', $request->validated('reviewer_person_id'))->firstOrFail();
        $review = $this->execute(fn (): PressPublicationReview => $action->handle(
            $target,
            $context->actor($request),
            PressReviewStage::from((string) $request->validated('stage')),
            PressReviewDecision::from((string) $request->validated('decision')),
            $reviewer,
            $request->validated('comments'),
            $request->validated('requested_changes'),
            $request->validated('checklist'),
            (bool) $request->validated('comments_public', false),
        ));
        $review->load(['publication:id,public_id,title', ...PersonDisplayName::eager('reviewer')]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($review))->resolve($request), status: 201);
    }

    public function storeAuthor(UpsertPressAuthorRequest $request, UpsertPressAuthorAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $person = Person::query()->where('public_id', $request->validated('person_id'))->firstOrFail();
        $author = $this->execute(fn (): PressAuthor => $action->handle(
            $person,
            (string) $request->validated('display_name'),
            $context->actor($request),
            $request->validated('bio'),
        ));
        $author->load(PersonDisplayName::eager());

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($author))->resolve($request), status: 201);
    }

    public function updateAuthor(Request $request, string $author, UpsertPressAuthorAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:191'],
            'bio' => ['nullable', 'string', 'max:10000'],
        ]);
        $target = PressAuthor::query()->with('person')->where('public_id', $author)->firstOrFail();
        $updated = $this->execute(fn (): PressAuthor => $action->handle(
            $target->person,
            $data['display_name'],
            $context->actor($request),
            $data['bio'] ?? null,
        ));
        $updated->load(PersonDisplayName::eager());

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($updated))->resolve($request));
    }

    public function storeTranslation(CreatePressTranslationRequest $request, string $publication, CreateMachinePressTranslationAction $action, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $target = $this->publication($publication);
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

    private function publication(string $publicId): PressPublication
    {
        return PressPublication::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function publicationData(CreatePressPublicationRequest|UpdatePressPublicationRequest $request): PressPublicationData
    {
        $cover = $request->validated('cover_file_asset_id') === null ? null : FileAsset::query()->where('public_id', $request->validated('cover_file_asset_id'))->firstOrFail();
        $content = $request->validated('content_file_asset_id') === null ? null : FileAsset::query()->where('public_id', $request->validated('content_file_asset_id'))->firstOrFail();

        return new PressPublicationData(
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
            contentSourceUrl: $request->validated('content_source_url'),
            priceMinor: $request->validated('price_minor') === null ? null : (int) $request->validated('price_minor'),
            currencyCode: $request->validated('currency_code'),
            publicationType: PressPublicationType::from((string) $request->validated('publication_type', PressPublicationType::Book->value)),
            visibility: PressPublicationVisibility::from((string) $request->validated('visibility', PressPublicationVisibility::Public->value)),
            asDraft: (bool) $request->validated('as_draft', false),
            featured: (bool) $request->validated('featured', false),
            slug: $request->validated('slug'),
            summary: $request->validated('summary'),
            typeMetadata: $request->validated('type_metadata', []),
        );
    }
}
