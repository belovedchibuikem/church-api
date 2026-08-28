<?php

namespace App\Models;

use App\Exceptions\FinancialRecordImmutableException;
use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
#[Hidden(['provider_event_hash', 'provider_reference_hash'])]
class PaymentTransaction extends Model
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(PaymentReconciliation::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new FinancialRecordImmutableException);
        static::deleting(fn (): never => throw new FinancialRecordImmutableException);
    }
}
