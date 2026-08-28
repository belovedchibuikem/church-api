<?php

namespace App\Models;

use App\Privacy\DataSubjectRequestStatus;
use App\Privacy\DataSubjectRequestType;
use Database\Factories\DataSubjectRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_type', 'request_notes'])]
#[Hidden(['idempotency_key_hash', 'request_notes', 'data_categories'])]
class DataSubjectRequest extends Model
{
    /** @use HasFactory<DataSubjectRequestFactory> */
    use HasFactory, HasUlids;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function exportFileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'export_file_asset_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'request_type' => DataSubjectRequestType::class,
            'status' => DataSubjectRequestStatus::class,
            'request_notes' => 'encrypted',
            'data_categories' => 'encrypted:array',
            'requested_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'export_expires_at' => 'immutable_datetime',
        ];
    }
}
