<?php

namespace App\Privacy\Actions;

use App\Models\DataSubjectRequest;
use App\Models\Person;
use App\Models\User;
use App\Privacy\DataSubjectRequestStatus;
use App\Privacy\DataSubjectRequestType;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SubmitDataSubjectRequestAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        Person $person,
        DataSubjectRequestType $type,
        string $idempotencyKey,
        ?string $notes = null,
        ?User $actor = null,
    ): DataSubjectRequest {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('A privacy request idempotency key is required.');
        }

        $keyHash = hash_hmac('sha256', $idempotencyKey, (string) config('app.key'));

        return DB::transaction(function () use ($person, $type, $keyHash, $notes, $actor): DataSubjectRequest {
            $existing = DataSubjectRequest::query()
                ->where('idempotency_key_hash', $keyHash)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->person_id !== $person->getKey() || $existing->request_type !== $type) {
                    throw new InvalidArgumentException('The privacy idempotency key is already in use.');
                }

                return $existing;
            }

            $request = new DataSubjectRequest([
                'request_type' => $type,
                'request_notes' => $notes,
            ]);
            $request->person()->associate($person);
            $request->requestedBy()->associate($actor);
            $request->status = DataSubjectRequestStatus::PendingReview;
            $request->idempotency_key_hash = $keyHash;
            $request->requested_at = now()->utc();
            $request->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'privacy.data_subject_request.submitted',
                actor: $actor,
                targetType: 'data_subject_request',
                targetId: $request->public_id,
                metadata: [
                    'request_type' => $type->value,
                    'status' => $request->status->value,
                ],
            ));

            return $request;
        }, attempts: 3);
    }
}
