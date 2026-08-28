<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Communication\CommunicationChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCommunicationTemplateRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:100'],
            'channel' => ['required', Rule::enum(CommunicationChannel::class)],
            'locale' => ['required', 'string', 'max:35'],
            'subject' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string'],
        ];
    }
}
