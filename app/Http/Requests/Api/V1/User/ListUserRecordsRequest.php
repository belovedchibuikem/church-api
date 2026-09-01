<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUserRecordsRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'q' => ['sometimes', 'string', 'max:191'],
            'country' => ['sometimes', 'string', 'max:8'],
            'region' => ['sometimes', 'string', 'max:191'],
            'locality' => ['sometimes', 'string', 'max:191'],
            'scope' => ['sometimes', 'string', Rule::in([
                'all',
                'own_country',
                'own_state',
                'own_lga',
                'other_country',
                'other_state',
                'other_lga',
            ])],
            'filter' => ['sometimes', 'array'],
            'filter.when' => ['sometimes', 'string', Rule::in(['upcoming', 'past', 'all'])],
            'filter.q' => ['sometimes', 'string', 'max:191'],
            'filter.country' => ['sometimes', 'string', 'max:8'],
            'filter.region' => ['sometimes', 'string', 'max:191'],
            'filter.locality' => ['sometimes', 'string', 'max:191'],
            'filter.scope' => ['sometimes', 'string', Rule::in([
                'all',
                'own_country',
                'own_state',
                'own_lga',
                'other_country',
                'other_state',
                'other_lga',
            ])],
        ];
    }
}
