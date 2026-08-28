<?php

namespace App\Models;

use Database\Factories\GuardianConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purpose', 'policy_version', 'source', 'granted_at', 'expires_at'])]
class GuardianConsent extends Model
{
    /** @use HasFactory<GuardianConsentFactory> */
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

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(GuardianRelationship::class, 'guardian_relationship_id');
    }

    public function evidenceFileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'evidence_file_asset_id');
    }

    public function isActive(): bool
    {
        return $this->withdrawn_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'granted_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }
}
