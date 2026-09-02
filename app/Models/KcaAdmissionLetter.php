<?php

namespace App\Models;

use Database\Factories\KcaAdmissionLetterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kca_application_id',
    'reference_code',
    'batch_label',
    'letter_body',
    'signer_name',
    'signer_title',
    'letterhead_file_asset_id',
    'signature_file_asset_id',
    'issued_by_user_id',
    'issued_at',
])]
class KcaAdmissionLetter extends Model
{
    /** @use HasFactory<KcaAdmissionLetterFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(KcaApplication::class, 'kca_application_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function letterheadFile(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'letterhead_file_asset_id');
    }

    public function signatureFile(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'signature_file_asset_id');
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
        ];
    }
}
