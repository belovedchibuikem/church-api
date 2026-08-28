<?php

namespace App\Support\Press;

use App\Files\FileAssetStatus;
use App\Models\FileAsset;
use App\Models\PressPublication;
use App\Models\User;
use App\Press\PressIdempotency;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationData;
use App\Press\PressPublicationStatus;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreatePressPublicationAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(PressPublicationData $data, User $actor, string $idempotencyKey): PressPublication
    {
        $keyHash = PressIdempotency::keyHash($idempotencyKey);
        $fingerprint = PressIdempotency::fingerprint($data->fingerprintPayload());
        $existing = PressPublication::query()->where('idempotency_key_hash', $keyHash)->first();

        if ($existing !== null) {
            return $this->resolveRetry($existing, $fingerprint);
        }

        try {
            return DB::transaction(function () use ($data, $actor, $keyHash, $fingerprint): PressPublication {
                $this->assertAvailableAsset($data->coverFileAsset);
                $this->assertAvailableAsset($data->contentFileAsset);

                $publication = new PressPublication;
                $publication->forceFill([
                    'title' => $data->title,
                    'subtitle' => $data->subtitle,
                    'publisher_name' => $data->publisherName,
                    'edition' => $data->edition,
                    'publication_date' => $data->publicationDate,
                    'copyright_year' => $data->copyrightYear,
                    'language_code' => $data->languageCode,
                    'page_count' => $data->pageCount,
                    'category' => $data->category,
                    'description' => $data->description,
                    'cover_file_asset_id' => $data->coverFileAsset?->getKey(),
                    'content_file_asset_id' => $data->contentFileAsset?->getKey(),
                    'price_minor' => $data->priceMinor,
                    'currency_code' => $data->currencyCode,
                    'format' => $data->format,
                    'availability' => PressPublicationAvailability::Unavailable,
                    'status' => PressPublicationStatus::Manuscript,
                    'idempotency_key_hash' => $keyHash,
                    'request_fingerprint' => $fingerprint,
                    'status_changed_at' => now()->utc(),
                ]);
                $publication->save();

                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'press.publication.created',
                    actor: $actor,
                    targetType: 'press_publication',
                    targetId: $publication->public_id,
                    scopeType: 'press_publication',
                    scopeId: $publication->public_id,
                    metadata: [
                        'format' => $publication->format->value,
                        'language_code' => $publication->language_code,
                        'status' => $publication->status->value,
                    ],
                ));

                return $publication;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            $existing = PressPublication::query()->where('idempotency_key_hash', $keyHash)->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->resolveRetry($existing, $fingerprint);
        }
    }

    private function resolveRetry(PressPublication $publication, string $fingerprint): PressPublication
    {
        if (! hash_equals($publication->request_fingerprint, $fingerprint)) {
            throw new DomainException('The idempotency key was already used with different publication data.');
        }

        return $publication;
    }

    private function assertAvailableAsset(?FileAsset $asset): void
    {
        if ($asset === null) {
            return;
        }

        $lockedAsset = FileAsset::query()->lockForUpdate()->findOrFail($asset->getKey());

        if ($lockedAsset->status !== FileAssetStatus::Available || $lockedAsset->deleted_at !== null) {
            throw new DomainException('Press assets must be available and not deleted.');
        }
    }
}
