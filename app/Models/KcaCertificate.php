<?php

namespace App\Models;

use App\Exceptions\KcaCertificateImmutableException;
use Database\Factories\KcaCertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
#[Hidden(['verification_code_hash', 'issuance_key_hash'])]
class KcaCertificate extends Model
{
    /** @use HasFactory<KcaCertificateFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(KcaEnrollment::class, 'kca_enrollment_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function revocation(): HasOne
    {
        return $this->hasOne(KcaCertificateRevocation::class);
    }

    protected function casts(): array
    {
        return ['completion_on' => 'immutable_date', 'issued_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new KcaCertificateImmutableException;
        });

        static::deleting(function (): never {
            throw new KcaCertificateImmutableException;
        });
    }
}
