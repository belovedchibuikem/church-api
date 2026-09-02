<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'home_church_id',
    'service_date',
    'adults',
    'males',
    'females',
    'children',
    'first_timers',
    'notes',
])]
class HomeChurchAttendanceRecord extends Model
{
    use HasUlids;

    protected $attributes = [
        'adults' => 0,
        'males' => 0,
        'females' => 0,
        'children' => 0,
        'first_timers' => 0,
    ];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function homeChurch(): BelongsTo
    {
        return $this->belongsTo(HomeChurch::class);
    }

    public function headcount(): int
    {
        return (int) $this->males + (int) $this->females + (int) $this->children;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{males: int, females: int, children: int, adults: int, first_timers: int}
     */
    public static function countsFromPayload(array $data, ?self $existing = null): array
    {
        $males = $data['males'] ?? $data['male'] ?? null;
        $females = $data['females'] ?? $data['female'] ?? null;

        if ($males === null && $females === null && array_key_exists('adults', $data) && $data['adults'] !== null) {
            $males = (int) $data['adults'];
            $females = $existing?->females ?? 0;
        }

        $males = (int) ($males ?? $existing?->males ?? 0);
        $females = (int) ($females ?? $existing?->females ?? 0);
        $children = array_key_exists('children', $data) && $data['children'] !== null
            ? (int) $data['children']
            : (int) ($existing?->children ?? 0);
        $firstTimers = array_key_exists('first_timers', $data) && $data['first_timers'] !== null
            ? (int) $data['first_timers']
            : (int) ($existing?->first_timers ?? 0);

        return [
            'males' => $males,
            'females' => $females,
            'children' => $children,
            'adults' => $males + $females,
            'first_timers' => $firstTimers,
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'adults' => 'integer',
            'males' => 'integer',
            'females' => 'integer',
            'children' => 'integer',
            'first_timers' => 'integer',
        ];
    }
}
