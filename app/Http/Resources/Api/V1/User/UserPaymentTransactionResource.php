<?php

namespace App\Http\Resources\Api\V1\User;

use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentTransaction */
class UserPaymentTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'payment_intent_id' => $this->intent?->public_id,
            'receipt_id' => $this->receipt?->public_id,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
