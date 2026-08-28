<?php

namespace App\Models;

use Database\Factories\KcaCertificateRevocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class KcaCertificateRevocation extends Model
{
    /** @use HasFactory<KcaCertificateRevocationFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(KcaCertificate::class, 'kca_certificate_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    protected function casts(): array
    {
        return ['revoked_at' => 'immutable_datetime'];
    }
}
