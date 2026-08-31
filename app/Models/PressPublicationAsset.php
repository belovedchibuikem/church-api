<?php

namespace App\Models;

use App\Press\PressAssetFormat;
use App\Press\PressAssetProcessingStatus;
use Database\Factories\PressPublicationAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class PressPublicationAsset extends Model
{
    /** @use HasFactory<PressPublicationAssetFactory> */
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

    public function publication(): BelongsTo
    {
        return $this->belongsTo(PressPublication::class, 'press_publication_id');
    }

    public function fileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'asset_format' => PressAssetFormat::class,
            'processing_status' => PressAssetProcessingStatus::class,
            'version' => 'integer',
            'is_current' => 'boolean',
            'is_required' => 'boolean',
        ];
    }
}
