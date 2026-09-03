<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportKcaStudentsRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:10240'],
            'mode' => ['sometimes', 'string', 'in:enroll,application'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $file = $this->file('file');
            if ($file === null) {
                return;
            }
            $extension = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
                $validator->errors()->add('file', 'Upload a CSV or Excel (.xlsx) file.');
            }
        });
    }
}
