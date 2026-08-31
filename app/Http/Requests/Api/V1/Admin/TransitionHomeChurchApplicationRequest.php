<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Church\HomeChurchApplicationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionHomeChurchApplicationRequest extends FormRequest
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
            'status' => ['required', Rule::enum(HomeChurchApplicationStatus::class)],
            'reason_code' => ['required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'expected_status' => ['sometimes', 'nullable', Rule::enum(HomeChurchApplicationStatus::class)],
        ];
    }
}
