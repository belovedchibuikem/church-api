<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Admin\DashboardPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowAdminDashboardRequest extends FormRequest
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
            'preset' => ['sometimes', 'string', 'in:last_30_days,last_90_days,last_6_months,this_year,custom'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ];
    }

    public function period(): DashboardPeriod
    {
        /** @var array{preset?: string, from?: string, to?: string} $validated */
        $validated = $this->validated();

        return DashboardPeriod::resolve(
            $validated['preset'] ?? null,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );
    }

    public function currency(): string
    {
        return strtoupper((string) $this->validated('currency', 'NGN'));
    }
}
