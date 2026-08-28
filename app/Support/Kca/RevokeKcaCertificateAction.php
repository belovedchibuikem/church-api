<?php

namespace App\Support\Kca;

use App\Exceptions\KcaCertificateImmutableException;
use App\Models\KcaCertificate;
use App\Models\KcaCertificateRevocation;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RevokeKcaCertificateAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(KcaCertificate $certificate, string $reasonCode, ?string $notes, User $actor): KcaCertificateRevocation
    {
        $reason = trim($reasonCode);
        if ($reason === '' || strlen($reason) > 100) {
            throw new InvalidArgumentException('A revocation reason_code of 1 to 100 characters is required.');
        }

        return DB::transaction(function () use ($certificate, $reason, $notes, $actor): KcaCertificateRevocation {
            $existing = KcaCertificateRevocation::query()
                ->where('kca_certificate_id', $certificate->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            try {
                $revocation = new KcaCertificateRevocation;
                $revocation->forceFill([
                    'kca_certificate_id' => $certificate->getKey(),
                    'reason_code' => $reason,
                    'notes' => $notes,
                    'revoked_by_user_id' => $actor->getKey(),
                    'revoked_at' => now()->utc(),
                ])->save();
            } catch (KcaCertificateImmutableException $exception) {
                throw $exception;
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.certificate.revoked',
                actor: $actor,
                targetType: 'kca_certificate',
                targetId: $certificate->public_id,
                metadata: ['reason_code' => $reason, 'revocation_id' => $revocation->public_id],
            ));

            return $revocation;
        });
    }
}
