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
use App\Http\Requests\Api\V1\Admin\MoveAdministrativeUnitRequest;
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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
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
        ));

        return ApiResponse::success($request, (new CountryResource($country))->resolve($request), status: 201);
    }

    public function levels(
        ListAdministrativeLevelsRequest $request,
        string $country,
        OrganizationCatalogQuery $catalog,
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = Country::query()->where('public_id', $country)->firstOrFail();
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
        ProtectedAdminContext $context,
    ): JsonResponse {
        $target = Country::query()->where('public_id', $country)->firstOrFail();
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
