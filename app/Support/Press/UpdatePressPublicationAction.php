<?php

namespace App\Support\Press;

use App\Models\PressPublication;
use App\Models\User;
use App\Press\PressPublicationData;
use App\Press\PressPublicationStatus;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdatePressPublicationAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(PressPublication $publication, PressPublicationData $data, User $actor): PressPublication
    {
        return DB::transaction(function () use ($publication, $data, $actor): PressPublication {
            $locked = PressPublication::query()->lockForUpdate()->findOrFail($publication->getKey());

            if ($locked->status === PressPublicationStatus::Archived) {
                throw new DomainException('Archived publications cannot be edited.');
            }

            $locked->forceFill([
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
                'summary' => $data->summary,
                'slug' => $data->slug,
                'cover_file_asset_id' => $data->coverFileAsset?->getKey() ?? $locked->cover_file_asset_id,
                'content_file_asset_id' => $data->contentFileAsset?->getKey() ?? $locked->content_file_asset_id,
                'content_source_url' => $data->contentSourceUrl ?? $locked->content_source_url,
                'price_minor' => $data->priceMinor,
                'currency_code' => $data->currencyCode,
                'format' => $data->format,
                'publication_type' => $data->publicationType,
                'type_metadata' => $data->typeMetadata,
                'visibility' => $data->visibility,
                'featured' => $data->featured,
            ]);
            $locked->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'press.publication.updated',
                actor: $actor,
                targetType: 'press_publication',
                targetId: $locked->public_id,
                scopeType: 'press_publication',
                scopeId: $locked->public_id,
                metadata: [
                    'format' => $locked->format->value,
                    'publication_type' => $locked->publication_type->value,
                    'status' => $locked->status->value,
                ],
            ));

            return $locked;
        }, attempts: 3);
    }
}
