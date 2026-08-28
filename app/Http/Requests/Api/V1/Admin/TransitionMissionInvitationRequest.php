<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Mission\MissionInvitationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionMissionInvitationRequest extends FormRequest
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
            'status' => ['required', Rule::enum(MissionInvitationStatus::class)],
            'reason_code' => ['nullable', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/'],
        ];
    }
}
