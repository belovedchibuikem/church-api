<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Support\Api\ApiResponse;
use App\Support\Organization\IsoCountryCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Public read-only geography catalogue for Country → State → LGA selects.
 */
class PublicGeographyController extends Controller
{
    public function countries(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $query = Country::query()
            ->select(['public_id', 'iso_code', 'name'])
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('iso_code', 'like', '%'.strtoupper($search).'%');
            });
        }

        $items = $query->get()->map(static fn (Country $country): array => [
            'id' => $country->public_id,
            'code' => $country->iso_code,
            'name' => $country->name,
        ]);

        return ApiResponse::success($request, $items->all());
    }

    public function country(Request $request, string $country): JsonResponse
    {
        $model = $this->resolveCountry($country);
        $levels = AdministrativeLevel::query()
            ->whereBelongsTo($model)
            ->orderBy('sort_order')
            ->get(['public_id', 'code', 'name', 'sort_order'])
            ->map(static fn (AdministrativeLevel $level): array => [
                'id' => $level->public_id,
                'code' => $level->code,
                'name' => $level->name,
                'sort_order' => $level->sort_order,
            ]);

        return ApiResponse::success($request, [
            'id' => $model->public_id,
            'code' => $model->iso_code,
            'name' => $model->name,
            'levels' => $levels->all(),
        ]);
    }

    public function states(Request $request, string $country): JsonResponse
    {
        $model = $this->resolveCountry($country);
        $stateLevel = $this->firstLevel($model);
        $search = trim((string) $request->query('search', ''));

        $query = AdministrativeUnit::query()
            ->whereBelongsTo($model)
            ->where('administrative_level_id', $stateLevel->getKey())
            ->whereNull('parent_id')
            ->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $items = $query->get(['public_id', 'name', 'reference_code'])->map(
            static fn (AdministrativeUnit $unit): array => [
                'id' => $unit->public_id,
                'name' => $unit->name,
                'reference_code' => $unit->reference_code,
            ],
        );

        return ApiResponse::success($request, $items->all(), [
            'country' => ['code' => $model->iso_code, 'name' => $model->name],
            'level' => [
                'code' => $stateLevel->code,
                'name' => $stateLevel->name,
            ],
        ]);
    }

    public function localities(Request $request, string $country, string $state): JsonResponse
    {
        $model = $this->resolveCountry($country);
        $stateLevel = $this->firstLevel($model);
        $localLevel = $this->secondLevel($model);

        $stateUnit = AdministrativeUnit::query()
            ->whereBelongsTo($model)
            ->where('administrative_level_id', $stateLevel->getKey())
            ->where(function ($builder) use ($state): void {
                $builder->where('public_id', $state)
                    ->orWhere('reference_code', strtoupper($state))
                    ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($state)]);
            })
            ->firstOrFail();

        $search = trim((string) $request->query('search', ''));
        $query = AdministrativeUnit::query()
            ->whereBelongsTo($model)
            ->where('administrative_level_id', $localLevel->getKey())
            ->where('parent_id', $stateUnit->getKey())
            ->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $items = $query->get(['public_id', 'name', 'reference_code'])->map(
            static fn (AdministrativeUnit $unit): array => [
                'id' => $unit->public_id,
                'name' => $unit->name,
                'reference_code' => $unit->reference_code,
            ],
        );

        return ApiResponse::success($request, $items->all(), [
            'country' => ['code' => $model->iso_code, 'name' => $model->name],
            'state' => [
                'id' => $stateUnit->public_id,
                'name' => $stateUnit->name,
                'reference_code' => $stateUnit->reference_code,
            ],
            'level' => [
                'code' => $localLevel->code,
                'name' => $localLevel->name,
            ],
        ]);
    }

    private function resolveCountry(string $country): Country
    {
        $value = trim($country);
        $model = Country::query()
            ->where(function ($builder) use ($value): void {
                $builder->where('public_id', $value)
                    ->orWhere('iso_code', strtoupper($value));
            })
            ->first();

        if ($model !== null) {
            return $model;
        }

        try {
            new IsoCountryCode($value);
        } catch (InvalidArgumentException) {
            // fall through
        }

        throw ValidationException::withMessages([
            'country' => ['Country was not found in the geography catalogue.'],
        ]);
    }

    private function firstLevel(Country $country): AdministrativeLevel
    {
        $level = AdministrativeLevel::query()
            ->whereBelongsTo($country)
            ->orderBy('sort_order')
            ->first();

        if ($level === null) {
            throw ValidationException::withMessages([
                'country' => ['This country has no administrative levels configured.'],
            ]);
        }

        return $level;
    }

    private function secondLevel(Country $country): AdministrativeLevel
    {
        $levels = AdministrativeLevel::query()
            ->whereBelongsTo($country)
            ->orderBy('sort_order')
            ->get();

        $level = $levels->skip(1)->first() ?? $levels->first();
        if ($level === null) {
            throw ValidationException::withMessages([
                'country' => ['This country has no locality level configured.'],
            ]);
        }

        return $level;
    }
}
