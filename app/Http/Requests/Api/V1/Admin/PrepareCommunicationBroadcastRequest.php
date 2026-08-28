<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Communication\CommunicationChannel;
use App\Communication\CommunicationKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrepareCommunicationBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key') ?? $this->input('idempotency_key')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'between:1,255'],
            'template_id' => ['required', 'ulid', 'exists:communication_templates,public_id'],
            'audience_id' => ['required', 'ulid', 'exists:communication_audiences,public_id'],
            'kind' => ['required', Rule::enum(CommunicationKind::class)],
            'channel' => ['required', Rule::enum(CommunicationChannel::class)],
            'purpose' => ['required', 'string', 'max:100'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
