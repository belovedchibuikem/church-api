<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Branding\PlatformBrandingPresenter;
use App\Http\Controllers\Controller;
use App\Models\PlatformBrandingConfiguration;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PublicBrandingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuration = Schema::hasTable('platform_branding_configurations')
            ? PlatformBrandingConfiguration::query()->with(['logoFile', 'faviconFile'])->first()
            : null;
        $payload = PlatformBrandingPresenter::toArray($configuration);

        return ApiResponse::success($request, [
            'app_name' => $payload['app_name'],
            'logo_url' => $payload['logo_url'],
            'favicon_url' => $payload['favicon_url'],
        ]);
    }
}
