<?php

namespace App\Http\Requests\Api\V1\User;

use App\Support\Bible\BibleReadingPlanGenerator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollBiblePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $codes = array_column(BibleReadingPlanGenerator::summaries(), 'code');

        return [
            'plan_code' => ['required', 'string', Rule::in($codes)],
            'started_on' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
        ];
    }
}
