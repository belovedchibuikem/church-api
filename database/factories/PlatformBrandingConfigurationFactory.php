<?php

namespace Database\Factories;

use App\Models\PlatformBrandingConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformBrandingConfiguration>
 */
class PlatformBrandingConfigurationFactory extends Factory
{
    protected $model = PlatformBrandingConfiguration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_name' => 'Family House Connect',
            'logo_file_asset_id' => null,
            'favicon_file_asset_id' => null,
            'configuration_revision' => 1,
        ];
    }
}
