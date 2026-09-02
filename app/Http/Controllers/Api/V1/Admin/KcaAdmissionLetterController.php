<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Files\FileAssetStreamResponse;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IssueKcaAdmissionLetterRequest;
use App\Http\Resources\Api\V1\KcaAdmissionLetterResource;
use App\Models\FileAsset;
use App\Models\KcaAdmissionLetter;
use App\Models\KcaApplication;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Kca\IssueKcaAdmissionLetterAction;
use App\Support\Kca\KcaAdmissionLetterPdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class KcaAdmissionLetterController extends Controller
{
    use ExecutesDomainMutations;

    public function show(Request $request, string $application, ProtectedAdminContext $context): JsonResponse
    {
        $context->ensureGlobal($request);
        $letter = $this->findLetter($application);

        return ApiResponse::success($request, (new KcaAdmissionLetterResource($letter))->resolve($request));
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
        $letter = $this->findLetter($application);
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
        ProtectedAdminContext $context,
    ): StreamedResponse {
        $context->ensureGlobal($request);
        $letter = $this->findLetter($application);
        $allowedIds = array_filter([
            $letter->letterheadFile?->public_id,
            $letter->signatureFile?->public_id,
        ]);

        if (! in_array($file, $allowedIds, true)) {
            throw new NotFoundHttpException('Admission letter asset not found.');
        }

        $asset = FileAsset::query()->available()->where('public_id', $file)->firstOrFail();

        return $streams->handle($asset, $request->boolean('download', false));
    }

    private function findLetter(string $applicationPublicId): KcaAdmissionLetter
    {
        $target = KcaApplication::query()->where('public_id', $applicationPublicId)->firstOrFail();

        return KcaAdmissionLetter::query()
            ->with([
                'application.person.profile',
                'letterheadFile:id,public_id',
                'signatureFile:id,public_id',
            ])
            ->where('kca_application_id', $target->getKey())
            ->firstOrFail();
    }
}
