<?php

namespace App\Models;

use Database\Factories\MfaRecoveryCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mfa_method_id', 'code_hash'])]
#[Hidden(['code_hash'])]
class MfaRecoveryCode extends Model
{
    /** @use HasFactory<MfaRecoveryCodeFactory> */
    use HasFactory, HasUlids;

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

    public function mfaMethod(): BelongsTo
    {
        return $this->belongsTo(MfaMethod::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code_hash' => 'hashed',
            'used_at' => 'immutable_datetime',
        ];
    }
}
