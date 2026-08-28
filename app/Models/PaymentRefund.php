<?php

namespace App\Models;

use App\Exceptions\FinancialRecordImmutableException;
use App\Finance\PaymentRefundStatus;
use Database\Factories\PaymentRefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['idempotency_scope_hash', 'payload_fingerprint'])]
class PaymentRefund extends Model
{
    /** @use HasFactory<PaymentRefundFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    protected function casts(): array
    {
        return ['status' => PaymentRefundStatus::class, 'amount_minor' => 'integer', 'requested_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new FinancialRecordImmutableException);
        static::deleting(fn (): never => throw new FinancialRecordImmutableException);
    }
}
