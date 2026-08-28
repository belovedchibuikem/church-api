<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Finance\PaymentProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurePaymentProviderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'active_provider' => ['required', 'string', Rule::enum(PaymentProvider::class)],
            'paystack_secret_key' => ['nullable', 'string', 'max:2048'],
            'paystack_public_key' => ['nullable', 'string', 'max:2048'],
            'paystack_webhook_secret' => ['nullable', 'string', 'max:2048'],
            'flutterwave_secret_key' => ['nullable', 'string', 'max:2048'],
            'flutterwave_public_key' => ['nullable', 'string', 'max:2048'],
            'flutterwave_webhook_secret' => ['nullable', 'string', 'max:2048'],
            'stripe_secret_key' => ['nullable', 'string', 'max:2048'],
            'stripe_publishable_key' => ['nullable', 'string', 'max:2048'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:2048'],
            'allowed_purpose_codes' => ['sometimes', 'array', 'min:1', 'max:20'],
            'allowed_purpose_codes.*' => ['string', 'max:100', 'regex:/\A[a-z][a-z0-9_]*\z/'],
            'allowed_currencies' => ['sometimes', 'array', 'min:1', 'max:10'],
            'allowed_currencies.*' => ['string', 'size:3', 'regex:/\A[A-Z]{3}\z/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $provider = $this->input('active_provider');
            $existing = \App\Models\PaymentProviderConfiguration::query()->first();

            $required = match ($provider) {
                PaymentProvider::Paystack->value => [
                    'paystack_secret_key' => $existing?->paystack_secret_key,
                    'paystack_public_key' => $existing?->paystack_public_key,
                    'paystack_webhook_secret' => $existing?->paystack_webhook_secret,
                ],
                PaymentProvider::Flutterwave->value => [
                    'flutterwave_secret_key' => $existing?->flutterwave_secret_key,
                    'flutterwave_public_key' => $existing?->flutterwave_public_key,
                    'flutterwave_webhook_secret' => $existing?->flutterwave_webhook_secret,
                ],
                PaymentProvider::Stripe->value => [
                    'stripe_secret_key' => $existing?->stripe_secret_key,
                    'stripe_publishable_key' => $existing?->stripe_publishable_key,
                    'stripe_webhook_secret' => $existing?->stripe_webhook_secret,
                ],
                default => [],
            };

            foreach ($required as $field => $stored) {
                if (blank($this->input($field)) && blank($stored)) {
                    $validator->errors()->add($field, 'This credential is required for the selected payment provider.');
                }
            }
        });
    }
}
