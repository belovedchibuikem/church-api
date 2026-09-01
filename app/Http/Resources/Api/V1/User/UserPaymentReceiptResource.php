<?php

namespace App\Http\Resources\Api\V1\User;

use App\Finance\GivingPurpose;
use App\Models\PaymentReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentReceipt */
class UserPaymentReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transaction = $this->transaction;
        $intent = $transaction?->intent;
        $purpose = $intent?->purpose_code;
        $provider = $transaction?->provider_code;
        $status = $intent?->status instanceof \BackedEnum
            ? $intent->status->value
            : ($intent?->status ?? 'recorded');

        return [
            'id' => $this->public_id,
            'receipt_number' => $this->receipt_number,
            'payment_transaction_id' => $transaction?->public_id,
            'payment_intent_id' => $intent?->public_id,
            'amount_minor' => $transaction?->amount_minor,
            'currency' => $transaction?->currency ?? 'NGN',
            'purpose_code' => $purpose,
            'purpose_label' => GivingPurpose::label($purpose),
            'provider_code' => $provider,
            'settlement' => $provider === 'local_manual' ? 'manual' : 'automatic',
            'status' => $status,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'occurred_at' => $transaction?->occurred_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
