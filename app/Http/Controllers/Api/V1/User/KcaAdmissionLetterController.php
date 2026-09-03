<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Files\FileAssetStreamResponse;
use App\Http\Controllers\Api\V1\User\Concerns\ResolvesAuthenticatedPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\AcceptKcaAdmissionLetterRequest;
use App\Http\Resources\Api\V1\KcaAdmissionLetterResource;
use App\Kca\KcaApplicationState;
use App\Models\FileAsset;
use App\Models\KcaAdmissionLetter;
use App\Models\KcaApplication;
use App\Support\Api\ApiResponse;
use App\Support\Kca\AcceptKcaAdmissionLetterAction;
use App\Support\Kca\KcaAdmissionLetterPdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class KcaAdmissionLetterController extends Controller
{
    use ResolvesAuthenticatedPerson;

    public function show(Request $request): JsonResponse
    {
        $letter = $this->requireLetter($request);

        return ApiResponse::success($request, (new KcaAdmissionLetterResource($letter))->resolve($request));
    }

    public function accept(
        AcceptKcaAdmissionLetterRequest $request,
        AcceptKcaAdmissionLetterAction $action,
    ): JsonResponse {
        $letter = $this->requireLetter($request);
        $validated = $request->validated();
        $updated = $action->handle(
            $letter,
            $request->user(),
            $validated['applicant_signature_name'],
            isset($validated['applicant_signature_file_asset_id'])
                ? FileAsset::query()->where('public_id', $validated['applicant_signature_file_asset_id'])->firstOrFail()
                : null,
            $validated['guardian_name'] ?? null,
            $validated['guardian_signature_name'] ?? null,
            $validated['guardian_phone'] ?? null,
        );

        return ApiResponse::success($request, (new KcaAdmissionLetterResource($updated))->resolve($request));
    }

    public function download(Request $request, KcaAdmissionLetterPdfRenderer $pdf): Response
    {
        $letter = $this->requireLetter($request);
        $bytes = $pdf->render($letter);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="kca-admission-letter.pdf"',
        ]);
    }

    public function streamAsset(
        Request $request,
        string $file,
        FileAssetStreamResponse $streams,
    ): StreamedResponse {
        $letter = $this->requireLetter($request);
        $allowedIds = array_filter([
            $letter->letterheadFile?->public_id,
            $letter->signatureFile?->public_id,
            $letter->applicantSignatureFile?->public_id,
        ]);

        if (! in_array($file, $allowedIds, true)) {
            throw new NotFoundHttpException('Admission letter asset not found.');
        }

        $asset = FileAsset::query()->available()->where('public_id', $file)->firstOrFail();

        return $streams->handle($asset, $request->boolean('download', false));
    }

    private function requireLetter(Request $request): KcaAdmissionLetter
    {
        $person = $this->person($request);
        $application = KcaApplication::query()
            ->where('person_id', $person->getKey())
            ->whereIn('status', [
                KcaApplicationState::Accepted->value,
                KcaApplicationState::ProvisionallyAccepted->value,
            ])
            ->latest('id')
            ->first();

        if ($application === null) {
            throw new NotFoundHttpException('No accepted KCA application is available.');
        }

        $letter = KcaAdmissionLetter::query()
            ->with([
                'application.person.profile',
                'letterheadFile',
                'signatureFile',
                'applicantSignatureFile',
            ])
            ->where('kca_application_id', $application->getKey())
            ->first();

        if ($letter === null) {
            throw new NotFoundHttpException('Your admission letter has not been issued yet.');
        }

        return $letter;
    }
}
