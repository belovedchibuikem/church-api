<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Press\PressContributorRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddPressPublicationContributorRequest extends FormRequest
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
            'person_id' => ['required', 'ulid', 'exists:people,public_id'],
            'role' => ['required', Rule::enum(PressContributorRole::class)],
        ];
    }
}
