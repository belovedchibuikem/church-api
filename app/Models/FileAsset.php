<?php

namespace App\Models;

use App\Files\FileAssetClassification;
use App\Files\FileAssetStatus;
use App\Files\MalwareScanStatus;
use App\Storage\StorageProvider;
use Database\Factories\FileAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['object_key', 'idempotency_key_hash', 'idempotency_scope_hash'])]
class FileAsset extends Model
{
    /** @use HasFactory<FileAssetFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => FileAssetStatus::Quarantined->value,
        'malware_scan_status' => MalwareScanStatus::Pending->value,
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'owner_person_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => FileAssetClassification::class,
            'storage_provider' => StorageProvider::class,
            'metadata' => 'array',
            'byte_size' => 'integer',
            'status' => FileAssetStatus::class,
            'malware_scan_status' => MalwareScanStatus::class,
            'malware_scanned_at' => 'immutable_datetime',
            'available_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    #[Scope]
    protected function available(Builder $query): Builder
    {
        return $query
            ->where('status', FileAssetStatus::Available->value)
            ->whereNull('deleted_at');
    }
}
