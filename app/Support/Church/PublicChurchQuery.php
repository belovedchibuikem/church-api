<?php

namespace App\Support\Church;

use App\Models\Church;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PublicChurchQuery
{
    /**
     * @param  array{name?: string, country?: string, administrative_unit?: string}  $filters
     * @return LengthAwarePaginator<int, Church>
     */
    public function paginate(array $filters, string $sort, int $perPage): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if (isset($filters['name'])) {
            $name = Str::of($filters['name'])
                ->trim()
                ->replace('\\', '\\\\')
                ->replace('%', '\\%')
                ->replace('_', '\\_')
                ->toString();

            $query->where('name', 'like', "{$name}%");
        }

        if (isset($filters['country'])) {
            $query->whereHas(
                'location.country',
                fn (Builder $countryQuery): Builder => $countryQuery->where('iso_code', Str::upper($filters['country'])),
            );
        }

        if (isset($filters['administrative_unit'])) {
            $query->whereHas(
                'administrativeUnit',
                fn (Builder $unitQuery): Builder => $unitQuery->where('public_id', $filters['administrative_unit']),
            );
        }

        [$column, $direction] = match ($sort) {
            '-name' => ['name', 'desc'],
            'published_at' => ['published_at', 'asc'],
            '-published_at' => ['published_at', 'desc'],
            default => ['name', 'asc'],
        };

        return $query
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublicOrFail(Church $church): Church
    {
        return $this->baseQuery()->findOrFail($church->getKey());
    }

    /** @return Builder<Church> */
    private function baseQuery(): Builder
    {
        return Church::query()
            ->select(['id', 'public_id', 'location_id', 'administrative_unit_id', 'name', 'published_at'])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->utc())
            ->with([
                'location:id,public_id,country_id,administrative_unit_id,name,locality,timezone',
                'location.country:id,public_id,iso_code,name',
                'location.administrativeUnit:id,public_id,name',
                'mediaAttachments.fileAsset',
                'homeChurches' => fn ($query) => $query
                    ->where('status', 'active')
                    ->select(['id', 'public_id', 'church_id', 'name', 'status', 'meeting_schedules']),
            ]);
    }
}
