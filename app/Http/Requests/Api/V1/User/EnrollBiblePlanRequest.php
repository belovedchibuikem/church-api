<?php

namespace App\Http\Requests\Api\V1\User;

use App\Support\Bible\BibleReadingPlanGenerator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
        return [
            'plan_code' => ['nullable', 'string', 'max:32'],
            'duration_days' => [
                'nullable',
                'integer',
                'min:'.BibleReadingPlanGenerator::MIN_CUSTOM_DAYS,
                'max:'.BibleReadingPlanGenerator::MAX_CUSTOM_DAYS,
            ],
            'started_on' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $code = $this->resolvedPlanCode();
            if ($code === null || ! BibleReadingPlanGenerator::isValidCode($code)) {
                $validator->errors()->add(
                    'plan_code',
                    'Choose a 3-month, 6-month, 1-year, or 2-year plan, or a custom length between '
                    .BibleReadingPlanGenerator::MIN_CUSTOM_DAYS
                    .' and '
                    .BibleReadingPlanGenerator::MAX_CUSTOM_DAYS
                    .' days.',
                );
            }
        });
    }

    public function resolvedPlanCode(): ?string
    {
        $code = trim((string) $this->input('plan_code', ''));
        if ($code !== '') {
            return $code;
        }
        $days = $this->input('duration_days');
        if ($days === null || $days === '') {
            return null;
        }

        return BibleReadingPlanGenerator::customCode((int) $days);
    }
}
