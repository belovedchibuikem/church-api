<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreateAdministrativeLevelRequest;
use App\Http\Requests\Api\V1\Admin\CreateAdministrativeUnitRequest;
use App\Http\Requests\Api\V1\Admin\CreateCountryRequest;
use App\Http\Requests\Api\V1\Admin\CreateLocationRequest;
use App\Http\Requests\Api\V1\Admin\ListAdministrativeLevelsRequest;
use App\Http\Requests\Api\V1\Admin\ListAdministrativeUnitsRequest;
use App\Http\Requests\Api\V1\Admin\ListCountriesRequest;
use App\Http\Requests\Api\V1\Admin\ListLocationsRequest;
use App\Http\Requests\Api\V1\Admin\ListTerritoryReportRequest;
use App\Http\Requests\Api\V1\Admin\MoveAdministrativeUnitRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdministrativeUnitRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCountryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateLocationRequest;
use App\Http\Resources\Api\V1\Admin\AdministrativeLevelResource;
use App\Http\Resources\Api\V1\Admin\AdministrativeUnitResource;
use App\Http\Resources\Api\V1\Admin\CountryResource;
use App\Http\Resources\Api\V1\Admin\LocationResource;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\Location;
use App\Queries\Admin\OrganizationCatalogQuery;
use App\Services\Admin\ProtectedAdminContext;
use App\Support\Api\ApiResponse;
use App\Support\Authorization\ScopeReference;
use App\Support\Organization\CreateAdministrativeLevelAction;
use App\Support\Organization\CreateAdministrativeUnitAction;
use App\Support\Organization\CreateCountryAction;
use App\Support\Organization\CreateLocationAction;
use App\Support\Organization\LocationData;
use App\Support\Organization\MoveAdministrativeUnitAction;
use App\Support\Organization\UpdateAdministrativeUnitAction;
use App\Support\Organization\UpdateCountryAction;
use App\Support\Organization\UpdateLocationAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OrganizationController extends Controller
{
    public function countries(
        ListCountriesRequest $request,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $validated = $request->validated();
        $paginator = $catalog->paginateCountries(
            $context->scope($request),
            $validated['filter'] ?? [],
            $validated['sort'] ?? 'name',
            (int) ($validated['per_page'] ?? 25),
        );

        return ApiResponse::success(
            $request,
            CountryResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => $this->pagination($paginator)],
        );
    }

    public function storeCountry(
        CreateCountryRequest $request,
        CreateCountryAction $createCountry,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $context->ensureGlobal($request);
        $country = $this->execute(fn (): Country => $createCountry->handle(
            (string) $request->validated('iso_code'),
            (string) $request->validated('name'),
            $context->actor($request),
            $request->safe()->except(['iso_code', 'name']),
        ));

        return ApiResponse::success($request, (new CountryResource($country))->resolve($request), status: 201);
    }

    public function showCountry(
        Request $request,
        string $country,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = $catalog->findCountry($country);
        $this->ensureCountryReadable($request, $target, $context);
        $payload = (new CountryResource($target))->resolve($request);
        $payload['stats'] = $catalog->countryStats($target);
        $payload['timezone'] = $target->default_timezone ?: $payload['stats']['timezone'];

        return ApiResponse::success($request, $payload);
    }

    public function updateCountry(
        UpdateCountryRequest $request,
        string $country,
        OrganizationCatalogQuery $catalog,
        UpdateCountryAction $updateCountry,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = $catalog->findCountry($country);
        $context->ensureContains($request, new ScopeReference('country', $target->public_id));
        $updated = $this->execute(fn (): Country => $updateCountry->handle(
            $target,
            (string) $request->validated('name'),
            $context->actor($request),
            $request->safe()->except(['name']),
        ));

        return ApiResponse::success($request, (new CountryResource($updated))->resolve($request));
    }

    public function destroyCountry(): JsonResponse
    {
        throw new ConflictHttpException('Countries are retained for organizational history. Rename the country instead of deleting it.');
    }

    public function levels(
        ListAdministrativeLevelsRequest $request,
        string $country,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = $catalog->findCountry($country);
        $this->ensureCountryReadable($request, $target, $context);

        return ApiResponse::success(
            $request,
            AdministrativeLevelResource::collection($catalog->levels($target))->resolve($request),
        );
    }

    public function storeLevel(
        CreateAdministrativeLevelRequest $request,
        string $country,
        CreateAdministrativeLevelAction $createLevel,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = $catalog->findCountry($country);
        $context->ensureContains($request, new ScopeReference('country', $target->public_id));
        $level = $this->execute(fn (): AdministrativeLevel => $createLevel->handle(
            $target,
            (string) $request->validated('code'),
            (string) $request->validated('name'),
            (int) $request->validated('sort_order'),
            $context->actor($request),
        ));
        $level->load('country:id,public_id');

        return ApiResponse::success($request, (new AdministrativeLevelResource($level))->resolve($request), status: 201);
    }

    public function units(
        ListAdministrativeUnitsRequest $request,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $validated = $request->validated();
        $paginator = $catalog->paginateUnits(
            $context->scope($request),
            $validated['filter'] ?? [],
            $validated['sort'] ?? 'name',
            (int) ($validated['per_page'] ?? 25),
        );

        return ApiResponse::success(
            $request,
            AdministrativeUnitResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => $this->pagination($paginator)],
        );
    }

    public function storeUnit(
        CreateAdministrativeUnitRequest $request,
        CreateAdministrativeUnitAction $createUnit,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $country = Country::query()->where('public_id', $request->validated('country_id'))->firstOrFail();
        $level = AdministrativeLevel::query()->where('public_id', $request->validated('administrative_level_id'))->firstOrFail();
        $parent = $request->validated('parent_id') === null
            ? null
            : AdministrativeUnit::query()->where('public_id', $request->validated('parent_id'))->firstOrFail();
        $context->ensureContains(
            $request,
            $parent === null
                ? new ScopeReference('country', $country->public_id)
                : new ScopeReference('administrative_unit', $parent->public_id),
        );
        $unit = $this->execute(fn (): AdministrativeUnit => $createUnit->handle(
            $country,
            $level,
            (string) $request->validated('name'),
            $parent,
            $request->validated('reference_code'),
            $context->actor($request),
        ));
        $unit->load(['country:id,public_id,iso_code,name', 'administrativeLevel:id,public_id,code,name,sort_order', 'parent:id,public_id,name']);

        return ApiResponse::success($request, (new AdministrativeUnitResource($unit))->resolve($request), status: 201);
    }

    public function moveUnit(
        MoveAdministrativeUnitRequest $request,
        string $unit,
        MoveAdministrativeUnitAction $moveUnit,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = AdministrativeUnit::query()->where('public_id', $unit)->firstOrFail();
        $parent = $request->validated('parent_id') === null
            ? null
            : AdministrativeUnit::query()->where('public_id', $request->validated('parent_id'))->firstOrFail();
        $context->ensureContains($request, new ScopeReference('administrative_unit', $target->public_id));

        if ($parent !== null) {
            $context->ensureContains($request, new ScopeReference('administrative_unit', $parent->public_id));
        } else {
            $country = Country::query()->findOrFail($target->country_id);
            $context->ensureContains($request, new ScopeReference('country', $country->public_id));
        }

        $updated = $this->execute(fn (): AdministrativeUnit => $moveUnit->handle(
            $target,
            $parent,
            $context->actor($request),
        ));
        $updated->load(['country:id,public_id,iso_code,name', 'administrativeLevel:id,public_id,code,name,sort_order', 'parent:id,public_id,name']);

        return ApiResponse::success($request, (new AdministrativeUnitResource($updated))->resolve($request));
    }

    public function showUnit(
        Request $request,
        string $unit,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = AdministrativeUnit::query()
            ->with(['country:id,public_id,iso_code,name', 'administrativeLevel:id,public_id,code,name,sort_order', 'parent:id,public_id,name'])
            ->where('public_id', $unit)
            ->firstOrFail();
        $this->ensureUnitReadable($request, $target, $context);
        $payload = (new AdministrativeUnitResource($target))->resolve($request);
        $payload['stats'] = $catalog->unitStats($target);

        return ApiResponse::success($request, $payload);
    }

    public function updateUnit(
        UpdateAdministrativeUnitRequest $request,
        string $unit,
        UpdateAdministrativeUnitAction $updateUnit,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = AdministrativeUnit::query()->where('public_id', $unit)->firstOrFail();
        $context->ensureContains($request, new ScopeReference('administrative_unit', $target->public_id));
        $updated = $this->execute(fn (): AdministrativeUnit => $updateUnit->handle(
            $target,
            (string) $request->validated('name'),
            $request->validated('reference_code'),
            $context->actor($request),
        ));
        $updated->load(['country:id,public_id,iso_code,name', 'administrativeLevel:id,public_id,code,name,sort_order', 'parent:id,public_id,name']);

        return ApiResponse::success($request, (new AdministrativeUnitResource($updated))->resolve($request));
    }

    public function destroyUnit(): JsonResponse
    {
        throw new ConflictHttpException('Administrative units are retained for organizational history. Reparent or rename instead of deleting.');
    }

    public function locations(
        ListLocationsRequest $request,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $validated = $request->validated();
        $paginator = $catalog->paginateLocations(
            $context->scope($request),
            $validated['filter'] ?? [],
            $validated['sort'] ?? 'name',
            (int) ($validated['per_page'] ?? 25),
        );

        return ApiResponse::success(
            $request,
            LocationResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => $this->pagination($paginator)],
        );
    }

    public function storeLocation(
        CreateLocationRequest $request,
        CreateLocationAction $createLocation,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $country = Country::query()->where('public_id', $request->validated('country_id'))->firstOrFail();
        $unit = $request->validated('administrative_unit_id') === null
            ? null
            : AdministrativeUnit::query()->where('public_id', $request->validated('administrative_unit_id'))->firstOrFail();
        $context->ensureContains(
            $request,
            $unit === null
                ? new ScopeReference('country', $country->public_id)
                : new ScopeReference('administrative_unit', $unit->public_id),
        );
        $location = $this->execute(fn (): Location => $createLocation->handle(new LocationData(
            country: $country,
            name: (string) $request->validated('name'),
            timezone: (string) $request->validated('timezone'),
            administrativeUnit: $unit,
            latitude: $request->validated('latitude') === null ? null : (float) $request->validated('latitude'),
            longitude: $request->validated('longitude') === null ? null : (float) $request->validated('longitude'),
            addressLineOne: $request->validated('address_line_one'),
            addressLineTwo: $request->validated('address_line_two'),
            locality: $request->validated('locality'),
            postalCode: $request->validated('postal_code'),
        ), $context->actor($request)));
        $location->load(['country:id,public_id,iso_code,name', 'administrativeUnit:id,public_id,name']);

        return ApiResponse::success($request, (new LocationResource($location))->resolve($request), status: 201);
    }

    public function showLocation(
        Request $request,
        string $location,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = Location::query()
            ->with(['country:id,public_id,iso_code,name', 'administrativeUnit:id,public_id,name'])
            ->where('public_id', $location)
            ->firstOrFail();
        $this->ensureLocationReadable($request, $target, $context);
        $payload = (new LocationResource($target))->resolve($request);
        $payload['stats'] = $catalog->locationStats($target);

        return ApiResponse::success($request, $payload);
    }

    public function updateLocation(
        UpdateLocationRequest $request,
        string $location,
        UpdateLocationAction $updateLocation,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = Location::query()->with('country')->where('public_id', $location)->firstOrFail();
        $unit = $request->validated('administrative_unit_id') === null
            ? null
            : AdministrativeUnit::query()->where('public_id', $request->validated('administrative_unit_id'))->firstOrFail();
        $context->ensureContains(
            $request,
            $unit === null
                ? new ScopeReference('country', $target->country->public_id)
                : new ScopeReference('administrative_unit', $unit->public_id),
        );
        $updated = $this->execute(fn (): Location => $updateLocation->handle($target, new LocationData(
            country: $target->country,
            name: (string) $request->validated('name'),
            timezone: (string) $request->validated('timezone'),
            administrativeUnit: $unit,
            latitude: $request->validated('latitude') === null ? null : (float) $request->validated('latitude'),
            longitude: $request->validated('longitude') === null ? null : (float) $request->validated('longitude'),
            addressLineOne: $request->validated('address_line_one'),
            addressLineTwo: $request->validated('address_line_two'),
            locality: $request->validated('locality'),
            postalCode: $request->validated('postal_code'),
        ), $context->actor($request)));
        $updated->load(['country:id,public_id,iso_code,name', 'administrativeUnit:id,public_id,name']);

        return ApiResponse::success($request, (new LocationResource($updated))->resolve($request));
    }

    public function destroyLocation(): JsonResponse
    {
        throw new ConflictHttpException('Locations are retained for organizational history. Update the address instead of deleting.');
    }

    public function map(
        ListLocationsRequest $request,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $validated = $request->validated();
        $filters = $validated['filter'] ?? [];
        $filters['has_coordinates'] = true;
        $paginator = $catalog->paginateLocations(
            $context->scope($request),
            $filters,
            $validated['sort'] ?? 'name',
            (int) ($validated['per_page'] ?? 100),
        );

        return ApiResponse::success(
            $request,
            LocationResource::collection($paginator->getCollection())->resolve($request),
            ['pagination' => $this->pagination($paginator)],
        );
    }

    public function territoryReport(
        ListTerritoryReportRequest $request,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $validated = $request->validated();
        $paginator = $catalog->paginateTerritory(
            $context->scope($request),
            $validated['filter'] ?? [],
            $validated['sort'] ?? 'name',
            (int) ($validated['per_page'] ?? 25),
        );
        $rows = $paginator->getCollection()->map(function (AdministrativeUnit $unit) use ($catalog): array {
            $base = (new AdministrativeUnitResource($unit))->resolve();
            $extra = $catalog->unitStats($unit);
            $base['stats'] = [
                'churches' => (int) $unit->churches_count,
                'locations' => (int) $unit->locations_count,
                'children' => (int) $unit->children_count,
                'home_churches' => $extra['home_churches'],
                'members' => $extra['members'],
            ];

            return $base;
        })->all();

        return ApiResponse::success($request, $rows, ['pagination' => $this->pagination($paginator)]);
    }

    public function churchTree(
        Request $request,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        return ApiResponse::success($request, $catalog->churchTree($context->scope($request)));
    }

    public function homeChurchTree(
        Request $request,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        return ApiResponse::success($request, $catalog->homeChurchTree($context->scope($request)));
    }

    private function ensureCountryReadable(
        Request $request,
        Country $country,
        ProtectedAdminContext $context,
    ): void {
        $scope = $context->scope($request);

        if ($context->isGlobal($scope) || ($scope->type === 'country' && $scope->key === $country->public_id)) {
            return;
        }

        if ($scope->type === 'administrative_unit') {
            $belongsToCountry = AdministrativeUnit::query()
                ->where('public_id', $scope->key)
                ->where('country_id', $country->getKey())
                ->exists();

            if ($belongsToCountry) {
                return;
            }
        }

        throw new NotFoundHttpException;
    }

    private function ensureUnitReadable(
        Request $request,
        AdministrativeUnit $unit,
        ProtectedAdminContext $context,
    ): void {
        $scope = $context->scope($request);

        if ($context->isGlobal($scope)) {
            return;
        }

        $country = $unit->country ?? Country::query()->find($unit->country_id);
        if ($scope->type === 'country' && $country !== null && $country->public_id === $scope->key) {
            return;
        }

        if ($scope->type === 'administrative_unit' && $this->catalogSubtreeContains($scope->key, (int) $unit->getKey())) {
            return;
        }

        throw new NotFoundHttpException;
    }

    private function ensureLocationReadable(
        Request $request,
        Location $location,
        ProtectedAdminContext $context,
    ): void {
        $scope = $context->scope($request);

        if ($context->isGlobal($scope)) {
            return;
        }

        $country = $location->country ?? Country::query()->find($location->country_id);
        if ($scope->type === 'country' && $country !== null && $country->public_id === $scope->key) {
            return;
        }

        if ($scope->type === 'administrative_unit' && $location->administrative_unit_id !== null) {
            $unit = AdministrativeUnit::query()->find($location->administrative_unit_id);
            if ($unit !== null) {
                $this->ensureUnitReadable($request, $unit, $context);

                return;
            }
        }

        throw new NotFoundHttpException;
    }

    private function catalogSubtreeContains(string $rootPublicId, int $unitId): bool
    {
        $root = AdministrativeUnit::query()->where('public_id', $rootPublicId)->first();
        if ($root === null) {
            return false;
        }
        if ((int) $root->getKey() === $unitId) {
            return true;
        }

        $units = AdministrativeUnit::query()
            ->select(['id', 'parent_id'])
            ->where('country_id', $root->country_id)
            ->get();
        $allowed = [$root->getKey() => true];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($units as $unit) {
                if (! isset($allowed[$unit->getKey()]) && isset($allowed[$unit->parent_id])) {
                    $allowed[$unit->getKey()] = true;
                    $changed = true;
                }
            }
        }

        return isset($allowed[$unitId]);
    }

    /** @return array{current_page: int, per_page: int, last_page: int, total: int} */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException(previous: $exception);
        }
    }
}
