<?php

namespace App\Support\Press;

use App\Models\PressPublication;
use App\Models\PressTranslation;
use App\Models\User;
use App\Press\PressIdempotency;
use App\Press\PressTranslationData;
use App\Press\PressTranslationStatus;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateMachinePressTranslationAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        PressPublication $publication,
        PressTranslationData $data,
        User $actor,
        string $idempotencyKey,
    ): PressTranslation {
        $keyHash = PressIdempotency::keyHash($idempotencyKey);
        $fingerprint = PressIdempotency::fingerprint([
            'publication_id' => $publication->getKey(),
            ...$data->fingerprintPayload(),
        ]);
        $existing = PressTranslation::query()->where('idempotency_key_hash', $keyHash)->first();

        if ($existing !== null) {
            return $this->resolveRetry($existing, $fingerprint);
        }

        try {
            return DB::transaction(function () use ($publication, $data, $actor, $keyHash, $fingerprint): PressTranslation {
                $lockedPublication = PressPublication::query()->lockForUpdate()->findOrFail($publication->getKey());

                if ($lockedPublication->language_code === $data->targetLanguageCode) {
                    throw new DomainException('Translation target language must differ from the publication language.');
                }

                $languageExisting = PressTranslation::query()
                    ->where('press_publication_id', $lockedPublication->getKey())
                    ->where('target_language_code', $data->targetLanguageCode)
                    ->first();

                if ($languageExisting !== null) {
                    throw new DomainException('A translation already exists for the target language.');
                }

                $translation = new PressTranslation;
                $translation->forceFill([
                    'press_publication_id' => $lockedPublication->getKey(),
                    'target_language_code' => $data->targetLanguageCode,
                    'translated_title' => $data->translatedTitle,
                    'translated_subtitle' => $data->translatedSubtitle,
                    'translated_description' => $data->translatedDescription,
                    'translated_content' => $data->translatedContent,
                    'status' => PressTranslationStatus::MachineGenerated,
                    'idempotency_key_hash' => $keyHash,
                    'request_fingerprint' => $fingerprint,
                    'status_changed_at' => now()->utc(),
                ]);
                $translation->save();

                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'press.translation.machine_generated',
                    actor: $actor,
                    targetType: 'press_translation',
                    targetId: $translation->public_id,
                    scopeType: 'press_publication',
                    scopeId: $lockedPublication->public_id,
                    metadata: [
                        'target_language_code' => $data->targetLanguageCode,
                        'status' => PressTranslationStatus::MachineGenerated->value,
                    ],
                ));

                return $translation;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            $existing = PressTranslation::query()->where('idempotency_key_hash', $keyHash)->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->resolveRetry($existing, $fingerprint);
        }
    }

    private function resolveRetry(PressTranslation $translation, string $fingerprint): PressTranslation
    {
        if (! hash_equals($translation->request_fingerprint, $fingerprint)) {
            throw new DomainException('The idempotency key was already used with different translation data.');
        }

        return $translation;
    }
}
