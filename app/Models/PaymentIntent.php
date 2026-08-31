<?php

namespace App\Models;

use App\Finance\PaymentIntentStatus;
use Database\Factories\PaymentIntentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden(['idempotency_scope_hash', 'payload_fingerprint'])]
class PaymentIntent extends Model
{
    /** @use HasFactory<PaymentIntentFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'payer_person_id');
    }

    public function eventRegistration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function proofFileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'proof_file_asset_id');
    }

    protected function casts(): array
    {
        return ['status' => PaymentIntentStatus::class, 'amount_minor' => 'integer', 'expires_at' => 'immutable_datetime', 'succeeded_at' => 'immutable_datetime'];
    }
}
