<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Church\ChurchMembershipStatus;
use App\Finance\GivingPurpose;
use App\Http\Controllers\Api\V1\Admin\Concerns\ExecutesDomainMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListProtectedDomainRecordsRequest;
use App\Http\Resources\Api\V1\Admin\ProtectedCatalogRecordResource;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Queries\Admin\ProtectedDomainCatalogQuery;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Church\RecordChurchGivingAction;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChurchFinanceOperationsController extends Controller
{
    use ExecutesDomainMutations;

    public function transactions(
        ListProtectedDomainRecordsRequest $request,
        ProtectedDomainCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $paginator = $catalog->churchGivingTransactions(
            $context->scope($request),
            $request->validated('filter', []),
            (int) $request->validated('per_page', 25),
        );

        return ApiResponse::success($request, ProtectedCatalogRecordResource::collection($paginator)->resolve($request), meta: [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function storeGiving(
        Request $request,
        RecordChurchGivingAction $action,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $request->merge([
            'idempotency_key' => $request->header('Idempotency-Key') ?? $request->input('idempotency_key'),
        ]);
        $data = $request->validate([
            'church_id' => ['required', 'ulid', 'exists:churches,public_id'],
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'regex:/\A[A-Za-z]{3}\z/'],
            'purpose_code' => ['required', 'string', Rule::in(GivingPurpose::codes())],
            'idempotency_key' => ['required', 'string', 'between:8,191'],
            'occurred_at' => ['nullable', 'date'],
            'channel' => ['nullable', 'string', 'max:40'],
        ]);
        $church = Church::query()->where('public_id', $data['church_id'])->firstOrFail();
        $context->ensureContains($request, $church->scopeReference());
        $payer = Person::query()->where('public_id', $data['person_id'])->firstOrFail();
        $member = ChurchMembership::query()
            ->where('church_id', $church->getKey())
            ->where('person_id', $payer->getKey())
            ->where('status', ChurchMembershipStatus::Active)
            ->where('active_marker', 1)
            ->first();
        if ($member === null) {
            abort(422, 'Giving can only be recorded for an active member of this church.');
        }

        $recorded = $this->execute(fn (): array => $action->handle(
            $church,
            $payer,
            (int) $data['amount_minor'],
            (string) $data['currency'],
            (string) $data['purpose_code'],
            (string) $data['idempotency_key'],
            $context->actor($request),
            isset($data['occurred_at']) ? \Carbon\CarbonImmutable::parse($data['occurred_at']) : null,
            $data['channel'] ?? null,
        ));

        /** @var PaymentTransaction $transaction */
        $transaction = $recorded['transaction'];
        $transaction->load([
            'intent:id,public_id,purpose_code,status,payer_person_id,currency,amount_minor,succeeded_at',
            ...PersonDisplayName::eager('intent.payer'),
            'receipt:id,public_id,receipt_number,issued_at,payment_transaction_id',
        ]);

        return ApiResponse::success($request, (new ProtectedCatalogRecordResource($transaction))->resolve($request), status: 201);
    }
}
