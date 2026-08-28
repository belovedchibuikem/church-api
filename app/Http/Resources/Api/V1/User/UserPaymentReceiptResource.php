<?php

namespace App\Http\Resources\Api\V1\User;

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
        return [
            'id' => $this->public_id,
            'receipt_number' => $this->receipt_number,
            'payment_transaction_id' => $this->transaction?->public_id,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
