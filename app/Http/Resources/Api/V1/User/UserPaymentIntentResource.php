<?php

namespace App\Http\Resources\Api\V1\User;

use App\Models\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentIntent */
class UserPaymentIntentResource extends JsonResource
{
    /** @var array<string, mixed>|null */
    private ?array $clientPayload = null;

    private ?string $providerCode = null;

    /**
     * @param  array<string, mixed>|null  $clientPayload
     */
    public function withCheckout(?array $clientPayload, ?string $providerCode = null): static
    {
        $this->clientPayload = $clientPayload;
        $this->providerCode = $providerCode;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestTransaction = $this->relationLoaded('transactions')
            ? $this->transactions->sortByDesc('id')->first()
            : null;

        return [
            'id' => $this->public_id,
            'purpose_code' => $this->purpose_code,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'provider_code' => $this->providerCode,
            'client_payload' => $this->clientPayload,
            'proof_file_asset_id' => $this->relationLoaded('proofFileAsset')
                ? $this->proofFileAsset?->public_id
                : null,
            'transaction_id' => $latestTransaction?->public_id,
            'receipt_id' => $latestTransaction?->receipt?->public_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'succeeded_at' => $this->succeeded_at?->toIso8601String(),
        ];
    }
}
