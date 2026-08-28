<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigureCommunicationProviderRequest extends FormRequest
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
            'email_provider' => ['sometimes', 'string', Rule::in(['none', 'resend', 'ses', 'mailgun', 'smtp'])],
            'email_sender_name' => ['nullable', 'string', 'max:120'],
            'email_sender_address' => ['nullable', 'email', 'max:191'],
            'email_api_key' => ['nullable', 'string', 'max:2048'],
            'sms_provider' => ['sometimes', 'string', Rule::in(['none', 'termii', 'twilio', 'africastalking'])],
            'sms_sender_id' => ['nullable', 'string', 'max:32'],
            'sms_api_key' => ['nullable', 'string', 'max:2048'],
            'sms_api_secret' => ['nullable', 'string', 'max:2048'],
            'whatsapp_provider' => ['sometimes', 'string', Rule::in(['none', 'meta', 'twilio'])],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:64'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:2048'],
            'push_provider' => ['sometimes', 'string', Rule::in(['none', 'fcm'])],
            'push_server_key' => ['nullable', 'string', 'max:4096'],
            'consent_required_channels' => ['sometimes', 'array', 'max:8'],
            'consent_required_channels.*' => ['string', Rule::in(['email', 'sms', 'whatsapp', 'push'])],
            'retry_max_attempts' => ['sometimes', 'integer', 'between:1,10'],
            'retry_backoff_seconds' => ['sometimes', 'integer', 'between:10,86400'],
        ];
    }
}
