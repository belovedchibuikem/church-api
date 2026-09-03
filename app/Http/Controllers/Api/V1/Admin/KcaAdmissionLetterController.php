<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Files\Actions\ApproveFileAssetAction;
use App\Files\FileAssetStreamResponse;
use App\Files\FileAssetStatus;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IssueKcaAdmissionLetterRequest;
use App\Http\Resources\Api\V1\KcaAdmissionLetterResource;
use App\Models\FileAsset;
use App\Models\KcaAdmissionLetter;
use App\Models\KcaApplication;
use App\Models\User;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Kca\BuildKcaAdmissionLetterPreviewAction;
use App\Support\Kca\IssueKcaAdmissionLetterAction;
use App\Support\Kca\KcaAdmissionLetterPdfRenderer;
use App\Support\Kca\ResolveKcaApplicationChurchName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class KcaAdmissionLetterController extends Controller
{
    use ExecutesDomainMutations;

    public function show(
        Request $request,
        string $application,
        BuildKcaAdmissionLetterPreviewAction $preview,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $target = $this->findApplication($application);
        $letter = $this->findLetterForApplication($target);

        if ($letter !== null) {
            return ApiResponse::success($request, (new KcaAdmissionLetterResource($letter))->resolve($request));
        }

        return ApiResponse::success($request, $preview->handle($target));
    }

    public function issue(
        IssueKcaAdmissionLetterRequest $request,
        string $application,
        IssueKcaAdmissionLetterAction $action,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $target = KcaApplication::query()->where('public_id', $application)->firstOrFail();
        $validated = $request->validated();
        $letter = $this->execute(fn (): KcaAdmissionLetter => $action->handle(
            $target,
            $context->actor($request),
            $validated['batch_label'] ?? null,
            $validated['letter_body'] ?? null,
            $validated['signer_name'] ?? null,
            $validated['signer_title'] ?? null,
            isset($validated['letterhead_file_asset_id'])
                ? FileAsset::query()->where('public_id', $validated['letterhead_file_asset_id'])->firstOrFail()
                : null,
            isset($validated['signature_file_asset_id'])
                ? FileAsset::query()->where('public_id', $validated['signature_file_asset_id'])->firstOrFail()
                : null,
        ));

        return ApiResponse::success(
            $request,
            (new KcaAdmissionLetterResource($letter))->resolve($request),
            status: 201,
        );
    }

    public function download(
        Request $request,
        string $application,
        KcaAdmissionLetterPdfRenderer $pdf,
        ProtectedAdminContext $context,
    ): Response {
        $context->ensureGlobal($request);
        $letter = $this->requireLetter($application);
        $bytes = $pdf->render($letter);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="kca-admission-letter.pdf"',
        ]);
    }

    public function streamAsset(
        Request $request,
        string $application,
        string $file,
        FileAssetStreamResponse $streams,
        ApproveFileAssetAction $approveFile,
        ProtectedAdminContext $context,
    ): StreamedResponse {
        $context->ensureGlobal($request);
        $target = $this->findApplication($application);
        $asset = $this->resolveStreamableAsset($target, $file, $approveFile, $context->actor($request));

        return $streams->handle($asset, $request->boolean('download', false));
    }

    private function findApplication(string $applicationPublicId): KcaApplication
    {
        return KcaApplication::query()
            ->with(['person.profile', 'enrollment.cohort:id,name,public_id'])
            ->where('public_id', $applicationPublicId)
            ->firstOrFail();
    }

    private function findLetterForApplication(KcaApplication $application): ?KcaAdmissionLetter
    {
        return KcaAdmissionLetter::query()
            ->with([
                'application.person.profile',
                'letterheadFile',
                'signatureFile',
            ])
            ->where('kca_application_id', $application->getKey())
            ->first();
    }

    private function requireLetter(string $applicationPublicId): KcaAdmissionLetter
    {
        $target = $this->findApplication($applicationPublicId);
        $letter = $this->findLetterForApplication($target);

        if ($letter === null) {
            throw new NotFoundHttpException('Admission letter has not been issued yet.');
        }

        return $letter;
    }

    /** @return array<int, string> */
    private function allowedAssetIds(KcaApplication $application): array
    {
        $letter = $this->findLetterForApplication($application);

        if ($letter !== null) {
            return array_values(array_filter([
                $letter->letterheadFile?->public_id,
                $letter->signatureFile?->public_id,
            ]));
        }

        $governance = app(ResolveKcaApplicationChurchName::class)
            ->governanceDefaults()
            ->loadMissing(['admissionLetterheadFile', 'admissionSignatureFile']);

        return array_values(array_filter([
            $governance->admissionLetterheadFile?->public_id,
            $governance->admissionSignatureFile?->public_id,
        ]));
    }

    private function resolveStreamableAsset(
        KcaApplication $application,
        string $filePublicId,
        ApproveFileAssetAction $approveFile,
        User $actor,
    ): FileAsset {
        if (! in_array($filePublicId, $this->allowedAssetIds($application), true)) {
            throw new NotFoundHttpException('Admission letter asset not found.');
        }

        $asset = FileAsset::query()
            ->where('public_id', $filePublicId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        if ($asset->status !== FileAssetStatus::Available) {
            $approveFile->handle($asset, $actor);
            $asset->refresh();
        }

        if ($asset->status !== FileAssetStatus::Available) {
            throw new NotFoundHttpException('Admission letter asset not found.');
        }

        return $asset;
    }
}
