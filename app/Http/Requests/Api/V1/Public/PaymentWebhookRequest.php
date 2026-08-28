<?php

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider_code' => ['required', 'string', 'max:100'],
            'event_id' => ['required', 'string', 'max:191'],
            'payload' => ['required', 'array'],
        ];
    }
}
