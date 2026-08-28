<?php

namespace App\Models;

use Database\Factories\PlatformBrandingConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'app_name',
    'logo_file_asset_id',
    'favicon_file_asset_id',
    'configuration_revision',
])]
class PlatformBrandingConfiguration extends Model
{
    /** @use HasFactory<PlatformBrandingConfigurationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'app_name' => 'Family House Connect',
        'configuration_revision' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logo_file_asset_id' => 'integer',
            'favicon_file_asset_id' => 'integer',
            'configuration_revision' => 'integer',
        ];
    }

    public function logoFile(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'logo_file_asset_id');
    }

    public function faviconFile(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'favicon_file_asset_id');
    }
}
