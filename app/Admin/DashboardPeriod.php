<?php

namespace App\Admin;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class DashboardPeriod
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $preset,
    ) {
        if ($this->from->greaterThan($this->to)) {
            throw new InvalidArgumentException('Dashboard period start must be on or before the end.');
        }
    }

    public static function resolve(?string $preset, ?string $from, ?string $to): self
    {
        $normalized = $preset !== null && $preset !== '' ? $preset : (($from !== null && $from !== '') || ($to !== null && $to !== '') ? 'custom' : 'last_6_months');
        $now = CarbonImmutable::now()->endOfDay();

        return match ($normalized) {
            'last_30_days' => new self($now->subDays(29)->startOfDay(), $now, 'last_30_days'),
            'last_90_days' => new self($now->subDays(89)->startOfDay(), $now, 'last_90_days'),
            'this_year' => new self($now->startOfYear(), $now, 'this_year'),
            'custom' => new self(
                CarbonImmutable::parse((string) $from)->startOfDay(),
                CarbonImmutable::parse((string) ($to ?: $from))->endOfDay(),
                'custom',
            ),
            default => new self($now->startOfMonth()->subMonths(5), $now, 'last_6_months'),
        };
    }

    /** @return array{from: string, to: string, preset: string, label: string} */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'preset' => $this->preset,
            'label' => match ($this->preset) {
                'last_30_days' => 'Last 30 days',
                'last_90_days' => 'Last 90 days',
                'this_year' => 'This year',
                'custom' => $this->from->toFormattedDateString().' – '.$this->to->toFormattedDateString(),
                default => 'Last 6 months',
            },
        ];
    }

    public function previous(): self
    {
        $seconds = max(1, $this->to->getTimestamp() - $this->from->getTimestamp());

        return new self(
            $this->from->subSeconds($seconds),
            $this->from->subSecond(),
            $this->preset,
        );
    }
}
