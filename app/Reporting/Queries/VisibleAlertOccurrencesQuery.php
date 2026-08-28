<?php

namespace App\Reporting\Queries;

use App\Models\AlertOccurrence;
use App\Models\User;
use App\Reporting\AlertOccurrenceStatus;
use App\Reporting\Contracts\AlertVisibilityPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VisibleAlertOccurrencesQuery
{
    public function __construct(private AlertVisibilityPolicy $visibilityPolicy) {}

    /** @return Collection<int, AlertOccurrence> */
    public function resolve(User $user, ?AlertOccurrenceStatus $status = null): Collection
    {
        return AlertOccurrence::query()
            ->with('rule')
            ->when(
                $status !== null,
                fn (Builder $query): Builder => $query->where('status', $status->value),
            )
            ->orderByDesc('opened_at')
            ->get()
            ->filter(fn (AlertOccurrence $occurrence): bool => $this->visibilityPolicy->allows($user, $occurrence))
            ->values();
    }
}
